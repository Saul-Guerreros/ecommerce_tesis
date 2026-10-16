import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import {
  AbstractControl, FormBuilder, FormGroup, ReactiveFormsModule, ValidationErrors, Validators,
} from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import {
  EstadoCivil, GuardarPersonalRequest, ProfesionCatalogo, ResumenCredito,
  SolicitudPersonal, TipoProfesionCatalogo, TipoVivienda,
} from '../../../core/models/credito.model';
import { CiudadCatalogo, DepartamentoCatalogo } from '../../../core/models/direccion.model';
import { CreditoService } from '../../../core/services/credito.service';
import { DireccionClienteService } from '../../../core/services/direccion-cliente.service';

/**
 * Paso 1 del wizard: Datos Personales, con sus 3 sub-pasos internos
 * (Identificación 1/3, Domicilio 2/3, Situación Personal 3/3). Los
 * sub-pasos NO son rutas: son un índice interno (subPaso) que decide
 * qué bloque del mismo FormGroup se muestra, igual que pide el
 * instructivo ("formulario multipaso de página única").
 *
 * Modo edición: cuando llega con ?editar=1 (desde el [Editar] de la
 * pantalla de Confirmación), el componente NO usa el wizard de
 * sub-pasos: muestra los 3 bloques juntos en una sola pantalla, con
 * encabezado "Editando 1 de 4" y botones Cancelar / ACTUALIZAR DATOS,
 * tal como lo muestra el instructivo para "Datos Laborales... Editando
 * 2 de 3". Cancelar y Actualizar vuelven los dos a /confirmacion.
 */
@Component({
  selector: 'app-paso-personal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterModule],
  templateUrl: './paso-personal.component.html',
  styleUrl: './paso-personal.component.css',
})
export class PasoPersonalComponent implements OnInit {
  form!: FormGroup;

  /** 1 = Identificación, 2 = Domicilio, 3 = Situación Personal. */
  subPaso = 1;

  modoEdicion = false;
  cargando = true;
  guardando = false;

  // Las dos listas son INDEPENDIENTES: ninguna filtra a la otra, porque
  // cualquier combinacion es valida (un Medico puede ser funcionario
  // publico, privado o independiente).
  tiposProfesion: TipoProfesionCatalogo[] = [];
  profesiones: ProfesionCatalogo[] = [];

  /** Totales del credito, para el panel lateral. */
  resumen: ResumenCredito | null = null;

  departamentos: DepartamentoCatalogo[] = [];
  private ciudades: CiudadCatalogo[] = [];
  ciudadesFiltradas: CiudadCatalogo[] = [];

  readonly estadosCiviles: { valor: EstadoCivil; etiqueta: string }[] = [
    { valor: 'SOLTERO', etiqueta: 'Soltero/a' },
    { valor: 'CASADO', etiqueta: 'Casado/a' },
    { valor: 'DIVORCIADO', etiqueta: 'Divorciado/a' },
    { valor: 'VIUDO', etiqueta: 'Viudo/a' },
    { valor: 'UNION_LIBRE', etiqueta: 'Unión libre' },
  ];

  readonly tiposVivienda: { valor: TipoVivienda; etiqueta: string }[] = [
    { valor: 'PROPIA', etiqueta: 'Casa propia' },
    { valor: 'ALQUILER', etiqueta: 'Alquiler' },
  ];

  constructor(
    private fb: FormBuilder,
    private creditoService: CreditoService,
    private direccionService: DireccionClienteService,
    private toastr: ToastrService,
    private router: Router,
    private route: ActivatedRoute,
  ) {}

  ngOnInit(): void {
    this.modoEdicion = this.route.snapshot.queryParamMap.get('editar') === '1';

    this.construirFormulario();
    this.cargar();
  }

  private construirFormulario(): void {
    this.form = this.fb.group({
      // 1/3 Identificación
      scr_cedula: ['', [Validators.required, Validators.pattern(/^[0-9]{5,15}$/)]],
      scr_nombre: ['', [Validators.required, Validators.maxLength(95)]],
      scr_apelli: ['', [Validators.required, Validators.maxLength(100)]],
      scr_fechna: ['', [Validators.required, this.validadorMayorEdad()]],
      scr_celula: ['', [Validators.required, Validators.pattern(/^\+?[0-9\s\-]{6,20}$/)]],
      scr_email: ['', [Validators.required, Validators.email]],

      // 2/3 Domicilio
      dep_id: [null as number | null, [Validators.required]],
      scr_ciuid: [null as number | null, [Validators.required]],
      scr_barrio: ['', [Validators.required, Validators.maxLength(60)]],
      scr_calle: ['', [Validators.required, Validators.maxLength(100)]],
      scr_nrocasa: ['', [Validators.maxLength(45)]],
      scr_referen: ['', [Validators.maxLength(250)]],

      // 3/3 Situación personal
      scr_estciv: ['', [Validators.required]],
      scr_tprid: [null as number | null, [Validators.required]],
      scr_proid: [null as number | null, [Validators.required]],
      scr_tipviv: ['', [Validators.required]],
      scr_sertit: ['', [Validators.maxLength(150)]],
      scr_relsti: ['', [Validators.maxLength(100)]],
    });

    this.form.get('scr_tipviv')!.valueChanges.subscribe(() => this.ajustarValidadoresVivienda());
  }

  private cargar(): void {
    this.direccionService.catalogos().subscribe({
      next: (resp) => {
        this.departamentos = resp.departamentos;
        this.ciudades = resp.ciudades;
      },
      error: () => this.toastr.error('No pudimos cargar los departamentos.', 'Error'),
    });

    this.creditoService.config().subscribe({
      next: (resp) => {
        this.tiposProfesion = resp.tipos_profesion;
        this.profesiones = resp.profesiones;
        this.resumen = resp.resumen;

        if (resp.personal) {
          this.precargarDesde(resp.personal);
        } else {
          // Sin borrador todavía: precarga solo lo que ya tiene el
          // perfil (punto "Datos precargados de tu perfil"). El cliente
          // puede modificarlo libremente; eso NO reescribe su perfil.
          this.form.patchValue({
            scr_cedula: resp.perfil.scr_cedula ?? '',
            scr_nombre: resp.perfil.scr_nombre ?? '',
            scr_apelli: resp.perfil.scr_apelli ?? '',
            scr_fechna: resp.perfil.scr_fechna ?? '',
            scr_celula: resp.perfil.scr_celula ?? '',
            scr_email: resp.perfil.scr_email ?? '',
          });
        }

        this.cargando = false;
      },
      error: () => {
        this.cargando = false;
        this.toastr.error('No pudimos cargar el formulario de crédito.', 'Error');
      },
    });
  }

  private precargarDesde(p: SolicitudPersonal): void {
    this.ciudadesFiltradas = p.dep_id
      ? this.ciudades.filter((c) => c.ciu_depid === p.dep_id)
      : [];

    this.form.patchValue({
      scr_cedula: p.scr_cedula,
      scr_nombre: p.scr_nombre,
      scr_apelli: p.scr_apelli,
      scr_fechna: p.scr_fechna,
      scr_celula: p.scr_celula,
      scr_email: p.scr_email,

      dep_id: p.dep_id,
      scr_ciuid: p.scr_ciuid,
      scr_barrio: p.scr_barrio,
      scr_calle: p.scr_calle,
      scr_nrocasa: p.scr_nrocasa ?? '',
      scr_referen: p.scr_referen ?? '',

      scr_estciv: p.scr_estciv,
      scr_tprid: p.scr_tprid,
      scr_proid: p.scr_proid,
      scr_tipviv: p.scr_tipviv,
      scr_sertit: p.scr_sertit ?? '',
      scr_relsti: p.scr_relsti ?? '',
    });

    this.ajustarValidadoresVivienda();
  }

  // ---------- Select encadenado: Departamento -> Ciudad ----------

  cambiarDepartamento(): void {
    const dep = Number(this.form.value.dep_id);
    this.ciudadesFiltradas = this.ciudades.filter((c) => c.ciu_depid === dep);
    this.form.patchValue({ scr_ciuid: null });
  }

  // ---------- Vivienda: ANDE/ESSAP solo con Casa propia ----------

  get esViviendaPropia(): boolean {
    return this.form.value.scr_tipviv === 'PROPIA';
  }

  /** Tarjetas-boton de tipo de vivienda, al estilo del checkout. */
  elegirVivienda(valor: TipoVivienda): void {
    this.form.patchValue({ scr_tipviv: valor });
    this.form.get('scr_tipviv')!.markAsTouched();
  }

  private ajustarValidadoresVivienda(): void {
    const control1 = this.form.get('scr_sertit')!;
    const control2 = this.form.get('scr_relsti')!;

    if (!this.esViviendaPropia) {
      // Mismo criterio que el backend (ck_scr_vivienda + withValidator):
      // sin casa propia, estos campos no aplican y se limpian para no
      // mandar datos viejos de una elección anterior.
      control1.setValue('', { emitEvent: false });
      control2.setValue('', { emitEvent: false });
    }
  }

  // ---------- Validador de mayoría de edad ----------

  private validadorMayorEdad() {
    return (control: AbstractControl): ValidationErrors | null => {
      if (!control.value) return null;

      const fecha = new Date(control.value);
      const limite = new Date();
      limite.setFullYear(limite.getFullYear() - 18);

      return fecha <= limite ? null : { menorEdad: true };
    };
  }

  // ---------- Navegación entre sub-pasos (modo wizard) ----------

  /** Controles que pertenecen a cada sub-paso, para validar solo ese bloque. */
  private controlesDelSubPaso(subPaso: number): string[] {
    if (subPaso === 1) return ['scr_cedula', 'scr_nombre', 'scr_apelli', 'scr_fechna', 'scr_celula', 'scr_email'];
    if (subPaso === 2) return ['dep_id', 'scr_ciuid', 'scr_barrio', 'scr_calle', 'scr_nrocasa', 'scr_referen'];
    return ['scr_estciv', 'scr_tprid', 'scr_proid', 'scr_tipviv', 'scr_sertit', 'scr_relsti'];
  }

  private subPasoValido(subPaso: number): boolean {
    const controles = this.controlesDelSubPaso(subPaso);
    controles.forEach((c) => this.form.get(c)!.markAsTouched());
    return controles.every((c) => this.form.get(c)!.valid);
  }

  siguiente(): void {
    if (!this.subPasoValido(this.subPaso)) {
      return;
    }

    if (this.subPaso < 3) {
      this.subPaso++;
      return;
    }

    // Cierre del Paso 1 completo: recién acá se guarda contra el backend.
    this.guardar();
  }

  atras(): void {
    if (this.subPaso > 1) {
      this.subPaso--;
      return;
    }
    this.router.navigate(['/carrito-de-compra']);
  }

  // ---------- Modo edición (desde Confirmación) ----------

  cancelarEdicion(): void {
    this.router.navigate(['/solicitar-credito/confirmacion']);
  }

  actualizarDatos(): void {
    this.form.markAllAsTouched();

    if (this.form.invalid) {
      this.toastr.warning('Revisá los campos marcados antes de actualizar.', 'Datos incompletos');
      return;
    }

    this.guardar(true);
  }

  // ---------- Guardado contra el backend ----------

  private guardar(volverAConfirmacion = false): void {
    if (this.guardando) return;

    this.guardando = true;

    // dep_id es auxiliar de la UI (solo filtra ciudades) y no existe
    // como columna; scr_tprid SI viaja, es FK propia de la solicitud.
    const { dep_id, ...datos } = this.form.value;
    const payload: GuardarPersonalRequest = datos;

    this.creditoService.guardarPersonal(payload).subscribe({
      next: () => {
        this.guardando = false;

        if (volverAConfirmacion || this.modoEdicion) {
          this.router.navigate(['/solicitar-credito/confirmacion']);
        } else {
          this.router.navigate(['/solicitar-credito/laboral']);
        }
      },
      error: (err) => {
        this.guardando = false;
        this.toastr.error(this.mensajeError(err), 'No se pudo guardar');
      },
    });
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.dirty || c.touched);
  }

  private mensajeError(err: any): string {
    const errores = err?.error?.errors;
    if (errores) {
      const primerCampo = Object.keys(errores)[0];
      return errores[primerCampo]?.[0] ?? 'Intentá nuevamente.';
    }
    return err?.error?.message ?? 'Intentá nuevamente.';
  }
}