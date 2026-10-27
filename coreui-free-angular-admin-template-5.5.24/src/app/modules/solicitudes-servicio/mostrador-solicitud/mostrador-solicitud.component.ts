import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { Subscription, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { SolicitudesServicioService } from '../service/solicitudes-servicio.service';
import { PanelServiciosService } from '../../panel-servicios/service/panel-servicios.service';
import {ClienteMostrador, LineaGarantia, MostradorConfig, MostradorRegistrado, ServicioReclamable, TipoServicioMostrador,} from '../models/mostrador.model';
import { TecnicoOpcion } from '../models/solicitud-servicio.model';
import { esDomingo, hoyIso } from '../../../core/validators/fecha-servicio';
interface FotoSeleccionada {
  archivo: File;
  preview: string;
}

type Paso = 1 | 2 | 3 | 4;

/** Campos del backend -> paso del wizard donde se corrigen. */
const PASO_DE_CAMPO: Record<string, Paso> = {
  sse_cliid: 1,
  sse_garant: 2, sse_dpdid: 2, sse_sseori: 2, sse_tdiid: 2, sse_marca: 2, sse_modelo: 2, sse_nroser: 2, sse_proble: 2, sse_acceso: 2,
  sse_sucid: 3, rse_tecid: 3, rse_estfis: 3, rse_clave: 3, rse_obs: 3, acepta_condiciones: 3,
};

/**
 * Ingreso presencial ("mostrador"): segundo canal de entrada al flujo de servicio técnico.
 * 1 Cliente · 2 Servicio y equipo · 3 Recepción · 4 Confirmación.
 * Al confirmar, el backend crea la solicitud y la recibe en una transacción (queda RECIBIDA).
 */
@Component({
  selector: 'app-mostrador-solicitud',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink, BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule],
  templateUrl: './mostrador-solicitud.component.html',
})
export class MostradorSolicitudComponent implements OnInit, OnDestroy {
    /** El local no atiende los domingos: el backend rechaza el alta de mostrador ese día. */
  readonly hoyEsDomingo = esDomingo(hoyIso());
  readonly pasos: { n: Paso; titulo: string }[] = [
    { n: 1, titulo: 'Cliente' },
    { n: 2, titulo: 'Servicio y equipo' },
    { n: 3, titulo: 'Recepción' },
    { n: 4, titulo: 'Confirmación' },
  ];


  paso: Paso = 1;
  config: MostradorConfig | null = null;
  cargandoConfig = true;

  // ----- Paso 1: cliente -----
  busqueda = new FormControl('', { nonNullable: true });
  buscando = false;
  resultados: ClienteMostrador[] = [];
  buscado = false;
  cliente: ClienteMostrador | null = null;
  mostrarAlta = false;
  guardandoCliente = false;
  formCliente: FormGroup;
  formDocumento: FormGroup;
  guardandoDocumento = false;

  // ----- Paso 2: servicio y equipo -----
  formServicio: FormGroup;
  lineas: LineaGarantia[] = [];
  cargandoLineas = false;
  // Paso 3: reclamo de garantía de un servicio anterior
  reclamables: ServicioReclamable[] = [];
  cargandoReclamables = false;
  /** true = se entró con ?reclamo=SS-...: cliente y equipo bloqueados. */
  reclamoBloqueado = false;
  private readonly camposEquipo = ['sse_tdiid', 'sse_marca', 'sse_modelo', 'sse_nroser'];

  // ----- Paso 3: recepción -----
  formRecepcion: FormGroup;
  fotos: FotoSeleccionada[] = [];
  errorFotos: string | null = null;
  verClave = false;

  // ----- Paso 4 -----
  guardando = false;
  registrado: MostradorRegistrado | null = null;

  private subs: Subscription[] = [];

  constructor(
    private fb: FormBuilder,
    private service: SolicitudesServicioService,
    private panel: PanelServiciosService,
    private toastr: ToastrService,
    private router: Router,
    private route: ActivatedRoute,
  ) {
    this.formCliente = this.fb.group({
      per_tpdid: [null as number | null, Validators.required],
      per_cedula: ['', [Validators.required, Validators.maxLength(20)]],
      per_nombr1: ['', [Validators.required, Validators.maxLength(45)]],
      per_nombr2: ['', Validators.maxLength(45)],
      per_apell1: ['', [Validators.required, Validators.maxLength(50)]],
      per_apell2: ['', Validators.maxLength(50)],
      celular: ['', [Validators.required, Validators.pattern(/^\+?[0-9\s\-]{6,20}$/)]],
      es_whatsapp: [true],
      correo: ['', [Validators.email, Validators.maxLength(100)]],
    });

    this.formDocumento = this.fb.group({
      per_tpdid: [null as number | null, Validators.required],
      per_cedula: ['', [Validators.required, Validators.maxLength(20)]],
    });

    this.formServicio = this.fb.group({
      tipo: ['REPARACION' as TipoServicioMostrador],
      sse_sseori: [null as string | null],
      sse_garant: [false],
      sse_dpdid: [null as number | null],
      sse_tdiid: [null as number | null, Validators.required],
      sse_marca: ['', [Validators.required, Validators.maxLength(50)]],
      sse_modelo: ['', [Validators.required, Validators.maxLength(80)]],
      sse_nroser: ['', Validators.maxLength(80)],
      sse_proble: ['', [Validators.required, Validators.minLength(10), Validators.maxLength(500)]],
      sse_acceso: ['', Validators.maxLength(255)],
    });

    this.formRecepcion = this.fb.group({
      sse_sucid: [null as number | null, Validators.required],
      rse_tecid: [null as number | null, Validators.required],
      rse_estfis: ['', [Validators.required, Validators.minLength(5), Validators.maxLength(500)]],
      rse_clave: ['', Validators.maxLength(100)],
      rse_obs: ['', Validators.maxLength(500)],
      accesorios: this.fb.array([]),
      acepta_condiciones: [false, Validators.requiredTrue],
    });
  }

  ngOnInit(): void {
    this.service.mostradorConfig().subscribe({
      next: (cfg) => {
        this.config = cfg;
        this.cargandoConfig = false;
        const principal = cfg.sucursales.find((s) => s.suc_id === cfg.sucursal_principal);
        const defecto = principal?.suc_id ?? (cfg.sucursales.length === 1 ? cfg.sucursales[0].suc_id : null);
        this.formRecepcion.patchValue({ sse_sucid: defecto });
        const ci = cfg.tipos_documento.find((t) => t.tpd_codigo === 'CI');
        this.formCliente.patchValue({ per_tpdid: ci?.tpd_id ?? null });
        this.formDocumento.patchValue({ per_tpdid: ci?.tpd_id ?? null });
      },
      error: () => {
        this.cargandoConfig = false;
        this.toastr.error('No se pudo cargar la configuración del mostrador', 'Error');
      },
    });

    // Búsqueda de cliente con debounce (estándar del panel).
    this.subs.push(
      this.busqueda.valueChanges.pipe(debounceTime(400), distinctUntilChanged()).subscribe((q) => this.buscar(q)),
    );

    // Tipo de servicio (Paso 3): reparación, garantía de compra o reclamo de un servicio anterior.
    this.subs.push(
      this.formServicio.get('tipo')!.valueChanges.subscribe((t: TipoServicioMostrador) => this.aplicarTipo(t)),
    );
    this.subs.push(
      this.formServicio.get('sse_sseori')!.valueChanges.subscribe((numero: string | null) => this.aplicarReclamable(numero)),
    );

    // Entrada desde el detalle: /servicios/solicitudes/mostrador?reclamo=SS-...
    const reclamo = this.route.snapshot.queryParamMap.get('reclamo');
    if (reclamo) this.precargarReclamo(reclamo);

    // Garantía de compra: la línea pasa a ser obligatoria.
    this.subs.push(
      this.formServicio.get('sse_garant')!.valueChanges.subscribe((g: boolean) => {
        const dpd = this.formServicio.get('sse_dpdid')!;
        dpd.setValidators(g ? [Validators.required] : []);
        if (!g) dpd.setValue(null);
        dpd.updateValueAndValidity();
        if (g && this.cliente) this.cargarLineas(this.cliente.cli_id);
      }),
    );

    // Cada sucursal tiene su taller: al cambiar de sucursal se vuelve a elegir el técnico.
    this.subs.push(
      this.formRecepcion.get('sse_sucid')!.valueChanges.subscribe(() => this.formRecepcion.patchValue({ rse_tecid: null })),
    );
  }

  ngOnDestroy(): void {
    this.subs.forEach((s) => s.unsubscribe());
    this.limpiarFotos();
  }

  // ================= Paso 1: cliente =================

  private buscar(q: string): void {
    const termino = q.trim();
    if (termino.length < 4) {
      this.resultados = [];
      this.buscado = false;
      return;
    }
    this.buscando = true;
    this.service.buscarClientes(termino).subscribe({
      next: (res) => {
        this.resultados = res.data;
        this.buscado = true;
        this.buscando = false;
      },
      error: () => {
        this.buscando = false;
        this.toastr.error('No se pudo buscar el cliente', 'Error');
      },
    });
  }

  seleccionar(c: ClienteMostrador): void {
    if (c.cli_estado !== 1) {
      this.toastr.warning('El cliente está inactivo. Actívelo desde Clientes para registrar el servicio.', 'Cliente inactivo');
      return;
    }
    this.cliente = c;
    this.mostrarAlta = false;
    this.lineas = [];
    this.reclamables = [];
    this.formServicio.patchValue({ tipo: 'REPARACION', sse_garant: false, sse_dpdid: null });
  }

  cambiarCliente(): void {
    if (this.reclamoBloqueado) return;
    this.cliente = null;
    this.lineas = [];
    this.reclamables = [];
    this.formServicio.patchValue({ tipo: 'REPARACION' });
  }

  // ================= Paso 3: reclamo de garantía =================

  get esReclamo(): boolean {
    return this.formServicio.get('tipo')!.value === 'RECLAMO';
  }

  get reclamableSeleccionado(): ServicioReclamable | undefined {
    return this.reclamables.find((r) => r.sse_numero === this.formServicio.get('sse_sseori')!.value);
  }

  private aplicarTipo(tipo: TipoServicioMostrador): void {
    const sseori = this.formServicio.get('sse_sseori')!;
    this.formServicio.patchValue({ sse_garant: tipo === 'GARANTIA' });

    if (tipo === 'RECLAMO') {
      sseori.setValidators([Validators.required]);
      if (this.cliente && !this.reclamoBloqueado) this.cargarReclamables(this.cliente.cli_id);
    } else {
      sseori.setValidators([]);
      sseori.setValue(null);
      this.camposEquipo.forEach((c) => this.formServicio.get(c)!.enable({ emitEvent: false }));
    }
    sseori.updateValueAndValidity({ emitEvent: false });
  }

  /** El equipo del reclamo es el de la solicitud original: se copia y se bloquea. */
  private aplicarReclamable(numero: string | null): void {
    const r = this.reclamables.find((x) => x.sse_numero === numero);
    if (!r) return;
    this.formServicio.patchValue({
      sse_tdiid: r.sse_tdiid, sse_marca: r.sse_marca, sse_modelo: r.sse_modelo, sse_nroser: r.sse_nroser ?? '',
    });
    this.camposEquipo.forEach((c) => this.formServicio.get(c)!.disable({ emitEvent: false }));
  }

  private cargarReclamables(cliId: number): void {
    this.cargandoReclamables = true;
    this.service.reclamablesCliente(cliId).subscribe({
      next: (res) => {
        this.reclamables = res.data;
        this.cargandoReclamables = false;
      },
      error: () => {
        this.cargandoReclamables = false;
        this.toastr.error('No se pudieron cargar los servicios con garantía', 'Error');
      },
    });
  }

  private precargarReclamo(numero: string): void {
    this.service.datosReclamo(numero).subscribe({
      next: (res) => {
        this.cliente = res.data.cliente;
        this.reclamables = [res.data.original];
        this.reclamoBloqueado = true;
        this.formServicio.patchValue({ tipo: 'RECLAMO' });
        this.formServicio.patchValue({ sse_sseori: res.data.original.sse_numero });
        this.formServicio.get('tipo')!.disable({ emitEvent: false });
        this.formServicio.get('sse_sseori')!.disable({ emitEvent: false });
        // Cliente y equipo ya están definidos: se describe el problema actual.
        this.paso = this.cliente.documento ? 2 : 1;
      },
      error: (err) => {
        const msg = err?.error?.errors ? Object.values(err.error.errors as Record<string, string[]>)[0]?.[0] : null;
        this.toastr.error(msg ?? 'No se pudo cargar el servicio a reclamar', 'Reclamo de garantía');
        this.router.navigate([], { queryParams: {} });
      },
    });
  }

  abrirAlta(): void {
    this.mostrarAlta = true;
    const q = this.busqueda.value.trim();
    // Si lo buscado parece un documento, se precarga.
    if (/^[0-9.\s-]{4,20}$/.test(q) && q.replace(/\D/g, '').length >= 5 && q.replace(/\D/g, '').length <= 10) {
      this.formCliente.patchValue({ per_cedula: q.replace(/\D/g, '') });
    } else if (q.includes('@')) {
      this.formCliente.patchValue({ correo: q });
    }
  }

  guardarCliente(): void {
    if (this.formCliente.invalid) {
      this.formCliente.markAllAsTouched();
      return;
    }
    const v = this.formCliente.getRawValue();
    this.guardandoCliente = true;
    this.service.crearCliente({ ...v, correo: v.correo?.trim() || null }).subscribe({
      next: (res) => {
        this.guardandoCliente = false;
        this.toastr.success(res.message, 'Éxito');
        this.seleccionar(res.data);
      },
      error: (err) => {
        this.guardandoCliente = false;
        this.aplicarErrores(err, this.formCliente, 'No se pudo registrar el cliente');
      },
    });
  }

  guardarDocumento(): void {
    if (!this.cliente || this.formDocumento.invalid) {
      this.formDocumento.markAllAsTouched();
      return;
    }
    this.guardandoDocumento = true;
    this.service.completarDocumento(this.cliente.cli_id, this.formDocumento.getRawValue()).subscribe({
      next: (res) => {
        this.guardandoDocumento = false;
        this.toastr.success(res.message, 'Éxito');
        this.cliente = res.data;
      },
      error: (err) => {
        this.guardandoDocumento = false;
        this.aplicarErrores(err, this.formDocumento, 'No se pudo registrar el documento');
      },
    });
  }

  get clienteListo(): boolean {
    return !!this.cliente && !!this.cliente.documento;
  }

  // ================= Paso 2: servicio y equipo =================

  private cargarLineas(cliId: number): void {
    this.cargandoLineas = true;
    this.service.garantiasCliente(cliId).subscribe({
      next: (res) => {
        this.lineas = res.data;
        this.cargandoLineas = false;
      },
      error: () => {
        this.cargandoLineas = false;
        this.toastr.error('No se pudieron cargar las compras en garantía', 'Error');
      },
    });
  }

  get esGarantia(): boolean {
    return !!this.formServicio.get('sse_garant')!.value;
  }

  get lineaSeleccionada(): LineaGarantia | undefined {
    return this.lineas.find((l) => l.dpd_id === this.formServicio.get('sse_dpdid')!.value);
  }

  // ================= Paso 3: recepción =================

  get accesorios(): FormArray<FormControl<string>> {
    return this.formRecepcion.get('accesorios') as FormArray<FormControl<string>>;
  }

  get tecnicosDisponibles(): TecnicoOpcion[] {
    const suc = this.formRecepcion.get('sse_sucid')!.value as number | null;
    if (!this.config || !suc) return [];
    return this.config.tecnicos.filter((t) => t.sucursales.includes(Number(suc)));
  }

  get nombreSucursal(): string {
    const suc = Number(this.formRecepcion.get('sse_sucid')!.value);
    return this.config?.sucursales.find((s) => s.suc_id === suc)?.suc_nombre ?? '—';
  }

  get nombreTecnico(): string {
    const id = Number(this.formRecepcion.get('rse_tecid')!.value);
    return this.config?.tecnicos.find((t) => t.usu_id === id)?.nombre ?? '—';
  }

  get nombreTipo(): string {
    const id = Number(this.formServicio.get('sse_tdiid')!.value);
    return this.config?.tipos_dispositivo.find((t) => t.tdi_id === id)?.tdi_descri ?? '—';
  }

  agregarAccesorio(valor = ''): void {
    this.accesorios.push(this.fb.nonNullable.control(valor, [Validators.required, Validators.maxLength(100)]));
  }

  quitarAccesorio(i: number): void {
    this.accesorios.removeAt(i);
  }

  /** Igual que A2: precarga el checklist con los accesorios declarados (separados por coma). */
  private precargarAccesorios(): void {
    if (this.accesorios.length > 0) return;
    (this.formServicio.get('sse_acceso')!.value ?? '')
      .split(/[,;\n]/)
      .map((a: string) => a.trim())
      .filter((a: string) => a.length > 0)
      .forEach((a: string) => this.agregarAccesorio(a.substring(0, 100)));
  }

  onFotos(event: Event): void {
    const input = event.target as HTMLInputElement;
    const max = this.config?.max_fotos ?? 8;
    const maxBytes = (this.config?.foto_max_kb ?? 5120) * 1024;
    this.errorFotos = null;

    for (const archivo of Array.from(input.files ?? [])) {
      if (this.fotos.length >= max) {
        this.errorFotos = `Máximo ${max} fotos.`;
        break;
      }
      if (!/^image\/(jpeg|png|webp)$/.test(archivo.type)) {
        this.errorFotos = `"${archivo.name}" no es una imagen JPG, PNG o WEBP.`;
        continue;
      }
      if (archivo.size > maxBytes) {
        this.errorFotos = `"${archivo.name}" supera el tamaño máximo permitido.`;
        continue;
      }
      this.fotos.push({ archivo, preview: URL.createObjectURL(archivo) });
    }
    input.value = '';
  }

  quitarFoto(i: number): void {
    URL.revokeObjectURL(this.fotos[i].preview);
    this.fotos.splice(i, 1);
  }

  private limpiarFotos(): void {
    this.fotos.forEach((f) => URL.revokeObjectURL(f.preview));
    this.fotos = [];
  }

  // ================= Navegación =================

  irA(destino: Paso): void {
    // Hacia atrás siempre se puede; hacia adelante, solo validando los pasos intermedios.
    if (destino < this.paso) {
      this.paso = destino;
      return;
    }
    for (let p = this.paso; p < destino; p++) {
      if (!this.pasoValido(p as Paso, true)) return;
    }
    if (destino === 3) this.precargarAccesorios();
    this.paso = destino;
  }

  siguiente(): void {
    this.irA((this.paso + 1) as Paso);
  }

  anterior(): void {
    if (this.paso > 1) this.paso = (this.paso - 1) as Paso;
  }

  private pasoValido(p: Paso, marcar: boolean): boolean {
    switch (p) {
      case 1:
        if (!this.cliente) {
          if (marcar) this.toastr.warning('Seleccione o registre el cliente.', 'Cliente');
          return false;
        }
        if (!this.cliente.documento) {
          if (marcar) this.toastr.warning('Complete el documento del cliente.', 'Cliente');
          return false;
        }
        return true;
      case 2:
        if (this.formServicio.invalid) {
          if (marcar) this.formServicio.markAllAsTouched();
          return false;
        }
        return true;
      case 3:
        if (this.fotos.length === 0) {
          this.errorFotos = 'Adjunte al menos una foto del equipo (respaldo de la tenencia).';
        }
        if (this.formRecepcion.invalid || this.fotos.length === 0) {
          if (marcar) this.formRecepcion.markAllAsTouched();
          return false;
        }
        return true;
      default:
        return true;
    }
  }

  // ================= Envío =================

  confirmar(): void {
    if (this.guardando || !this.cliente) return;
    for (const p of [1, 2, 3] as Paso[]) {
      if (!this.pasoValido(p, true)) {
        this.paso = p;
        return;
      }
    }

    const s = this.formServicio.getRawValue();
    const r = this.formRecepcion.getRawValue();
    const datos = new FormData();
    datos.append('sse_cliid', String(this.cliente.cli_id));
    datos.append('sse_sucid', String(r.sse_sucid));
    datos.append('sse_garant', s.sse_garant || s.tipo === 'RECLAMO' ? '1' : '0');
    if (s.tipo === 'RECLAMO' && s.sse_sseori) datos.append('sse_sseori', s.sse_sseori);
    if (s.sse_garant && s.sse_dpdid) datos.append('sse_dpdid', String(s.sse_dpdid));
    datos.append('sse_tdiid', String(s.sse_tdiid));
    datos.append('sse_marca', s.sse_marca);
    datos.append('sse_modelo', s.sse_modelo);
    if (s.sse_nroser) datos.append('sse_nroser', s.sse_nroser);
    datos.append('sse_proble', s.sse_proble);
    if (s.sse_acceso) datos.append('sse_acceso', s.sse_acceso);
    datos.append('rse_tecid', String(r.rse_tecid));
    datos.append('rse_estfis', r.rse_estfis);
    if (r.rse_clave) datos.append('rse_clave', r.rse_clave);
    if (r.rse_obs) datos.append('rse_obs', r.rse_obs);
    (r.accesorios as string[]).forEach((a) => datos.append('accesorios[]', a));
    datos.append('acepta_condiciones', '1');
    this.fotos.forEach((f) => datos.append('fotos[]', f.archivo));

    this.guardando = true;
    this.service.registrarMostrador(datos).subscribe({
      next: (res) => {
        this.guardando = false;
        this.registrado = res.data;
        this.toastr.success(res.message, 'Éxito');
        this.imprimirConstancia();
      },
      error: (err) => {
        this.guardando = false;
        this.manejarErrorRegistro(err);
      },
    });
  }

  imprimirConstancia(): void {
    if (!this.registrado) return;
    const { sse_id, sse_numero } = this.registrado;
    this.panel.constancia(sse_id, 'recepcion').subscribe({
      next: (pdf) => this.panel.abrirPdf(pdf, `constancia-recepcion-${sse_numero}.pdf`),
      error: () => this.toastr.error('No se pudo generar la constancia', 'Error'),
    });
  }

  nuevaSolicitud(): void {
    if (this.reclamoBloqueado) {
      // Se sale del modo reclamo: la siguiente solicitud es libre.
      this.router.navigate([], { queryParams: {} });
    }
    this.reclamoBloqueado = false;
    this.reclamables = [];
    this.formServicio.get('tipo')!.enable({ emitEvent: false });
    this.formServicio.get('sse_sseori')!.enable({ emitEvent: false });
    this.camposEquipo.forEach((c) => this.formServicio.get(c)!.enable({ emitEvent: false }));
    this.registrado = null;
    this.cliente = null;
    this.resultados = [];
    this.buscado = false;
    this.mostrarAlta = false;
    this.lineas = [];
    this.busqueda.setValue('', { emitEvent: false });
    const suc = this.formRecepcion.get('sse_sucid')!.value;
    this.formServicio.reset({ tipo: 'REPARACION', sse_sseori: null, sse_garant: false, sse_dpdid: null, sse_tdiid: null, sse_marca: '', sse_modelo: '', sse_nroser: '', sse_proble: '', sse_acceso: '' });
    this.formRecepcion.reset({ sse_sucid: suc, rse_tecid: null, rse_estfis: '', rse_clave: '', rse_obs: '', acepta_condiciones: false });
    this.accesorios.clear();
    this.limpiarFotos();
    this.errorFotos = null;
    this.verClave = false;
    this.paso = 1;
  }

  volverABandeja(): void {
    this.router.navigate(['/servicios/solicitudes']);
  }

  // ================= Errores =================

  campoInvalido(form: FormGroup, campo: string): boolean {
    const c = form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(form: FormGroup, campo: string): string | null {
    return form.get(campo)?.errors?.['backend'] ?? null;
  }

  private aplicarErrores(err: any, form: FormGroup, porDefecto: string): void {
    if (err?.status === 422 && err.error?.errors) {
      Object.entries(err.error.errors as Record<string, string[]>).forEach(([campo, msgs]) => {
        const c = form.get(campo);
        if (c) {
          c.setErrors({ backend: msgs[0] });
          c.markAsTouched();
        } else {
          this.toastr.warning(msgs[0], 'Validación');
        }
      });
      return;
    }
    this.toastr.error(err?.error?.message ?? porDefecto, 'Error');
  }

  /** Muestra cada 422 junto a su campo y lleva al primer paso con error. */
  private manejarErrorRegistro(err: any): void {
    if (!(err?.status === 422 && err.error?.errors)) {
      this.toastr.error(err?.error?.message ?? 'No se pudo registrar la solicitud', 'Error');
      return;
    }
    let primerPaso: Paso = 4;
    Object.entries(err.error.errors as Record<string, string[]>).forEach(([campo, msgs]) => {
      if (campo.startsWith('fotos')) {
        this.errorFotos = msgs[0];
        primerPaso = Math.min(primerPaso, 3) as Paso;
        return;
      }
      if (campo.startsWith('accesorios')) {
        this.toastr.warning(msgs[0], 'Accesorios');
        primerPaso = Math.min(primerPaso, 3) as Paso;
        return;
      }
      const paso = PASO_DE_CAMPO[campo] ?? 4;
      primerPaso = Math.min(primerPaso, paso) as Paso;
      const form = paso === 2 ? this.formServicio : paso === 3 ? this.formRecepcion : null;
      const c = form?.get(campo);
      if (c) {
        c.setErrors({ backend: msgs[0] });
        c.markAsTouched();
      } else {
        this.toastr.warning(msgs[0], 'Validación');
      }
    });
    this.paso = primerPaso;
    this.toastr.error('Revise los datos ingresados', 'Validación');
  }
}
