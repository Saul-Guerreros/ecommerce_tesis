import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { SolicitudServicioDetalle, SolicitudServicioLista, TecnicoOpcion } from '../models/solicitud-servicio.model';
import { SolicitudesServicioService } from '../service/solicitudes-servicio.service';

interface FotoSeleccionada {
  archivo: File;
  preview: string;
}

/**
 * A2 - Confirmar recepción. Las fotos son obligatorias: son el respaldo
 * legal de que el equipo entró al taller y en qué estado.
 */
@Component({
  selector: 'app-confirmar-recepcion',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './confirmar-recepcion.component.html',
})
export class ConfirmarRecepcionComponent implements OnChanges {
  @Input() visible = false;
  @Input() solicitud: SolicitudServicioLista | null = null;
  @Input() tecnicos: TecnicoOpcion[] = [];

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  readonly MAX_FOTOS = 8;
  readonly MAX_MB = 5;

  form: FormGroup;
  detalle: SolicitudServicioDetalle | null = null;
  fotos: FotoSeleccionada[] = [];
  errorFotos: string | null = null;
  cargandoDetalle = false;
  guardando = false;
  verClave = false;

  constructor(private fb: FormBuilder, private service: SolicitudesServicioService, private toastr: ToastrService) {
    this.form = this.fb.group({
      rse_tecid: [null as number | null, Validators.required],
      rse_estfis: ['', [Validators.required, Validators.minLength(5), Validators.maxLength(500)]],
      rse_clave: ['', Validators.maxLength(100)],
      rse_obs: ['', Validators.maxLength(500)],
      accesorios: this.fb.array([]),
    });
  }

  /**
   * Cada sucursal tiene su taller: solo los técnicos asignados a la sucursal de la solicitud.
   * Sin sucursal (retiro a domicilio sin tarifa) se muestran todos los técnicos activos.
   */
  get tecnicosDisponibles(): TecnicoOpcion[] {
    const suc = this.detalle?.ingreso.suc_id ?? null;
    return suc ? this.tecnicos.filter((t) => (t.sucursales ?? []).includes(suc)) : this.tecnicos;
  }

  get accesorios(): FormArray<FormControl<string>> {
    return this.form.get('accesorios') as FormArray<FormControl<string>>;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible && this.solicitud) {
      this.reiniciar();
      this.cargarDetalle(this.solicitud.sse_id);
    }
  }

  private reiniciar(): void {
    this.form.reset({ rse_tecid: null, rse_estfis: '', rse_clave: '', rse_obs: '' });
    this.accesorios.clear();
    this.limpiarFotos();
    this.errorFotos = null;
    this.detalle = null;
    this.guardando = false;
    this.verClave = false;
  }

  /** Precarga el checklist con lo que el cliente declaró en el wizard (separado por comas). */
  private cargarDetalle(id: number): void {
    this.cargandoDetalle = true;
    this.service.show(id).subscribe({
      next: (res) => {
        this.detalle = res.data;
        (res.data.sse_acceso ?? '')
          .split(/[,;\n]/)
          .map((a) => a.trim())
          .filter((a) => a.length > 0)
          .forEach((a) => this.agregarAccesorio(a.substring(0, 100)));
        this.cargandoDetalle = false;
      },
      error: () => {
        this.cargandoDetalle = false;
        this.toastr.error('No se pudo cargar la solicitud', 'Error');
      },
    });
  }

  agregarAccesorio(valor = ''): void {
    this.accesorios.push(this.fb.nonNullable.control(valor, [Validators.required, Validators.maxLength(100)]));
  }

  quitarAccesorio(i: number): void {
    this.accesorios.removeAt(i);
  }

  // ---------- Fotos ----------

  onFotos(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivos = Array.from(input.files ?? []);
    this.errorFotos = null;

    for (const archivo of archivos) {
      if (this.fotos.length >= this.MAX_FOTOS) {
        this.errorFotos = `Máximo ${this.MAX_FOTOS} fotos.`;
        break;
      }
      if (!/^image\/(jpeg|png|webp)$/.test(archivo.type)) {
        this.errorFotos = `"${archivo.name}" no es una imagen JPG, PNG o WEBP.`;
        continue;
      }
      if (archivo.size > this.MAX_MB * 1024 * 1024) {
        this.errorFotos = `"${archivo.name}" supera los ${this.MAX_MB} MB.`;
        continue;
      }
      this.fotos.push({ archivo, preview: URL.createObjectURL(archivo) });
    }
    input.value = ''; // permite volver a elegir el mismo archivo
  }

  quitarFoto(i: number): void {
    URL.revokeObjectURL(this.fotos[i].preview);
    this.fotos.splice(i, 1);
  }

  private limpiarFotos(): void {
    this.fotos.forEach((f) => URL.revokeObjectURL(f.preview));
    this.fotos = [];
  }

  // ---------- Validación ----------

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  // ---------- Envío ----------

  guardar(): void {
    if (this.fotos.length === 0) {
      this.errorFotos = 'Adjunte al menos una foto del equipo (respaldo de la tenencia).';
    }
    if (this.form.invalid || this.fotos.length === 0 || !this.solicitud) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    const datos = new FormData();
    datos.append('rse_tecid', String(v.rse_tecid));
    datos.append('rse_estfis', v.rse_estfis);
    if (v.rse_clave) datos.append('rse_clave', v.rse_clave);
    if (v.rse_obs) datos.append('rse_obs', v.rse_obs);
    (v.accesorios as string[]).forEach((a) => datos.append('accesorios[]', a));
    this.fotos.forEach((f) => datos.append('fotos[]', f.archivo));

    this.guardando = true;
    this.service.confirmarRecepcion(this.solicitud.sse_id, datos).subscribe({
      next: (res) => {
        this.toastr.success(res.message, 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          Object.entries(err.error.errors as Record<string, string[]>).forEach(([campo, msgs]) => {
            if (campo.startsWith('fotos')) {
              this.errorFotos = msgs[0];
            } else if (campo.startsWith('accesorios')) {
              this.toastr.warning(msgs[0], 'Accesorios');
            } else {
              this.form.get(campo)?.setErrors({ backend: msgs[0] });
              this.form.get(campo)?.markAsTouched();
            }
          });
          this.toastr.error('Revise los datos ingresados', 'Validación');
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo registrar la recepción', 'Error');
      },
    });
  }

  cerrar(): void {
    this.limpiarFotos();
    this.visible = false;
    this.visibleChange.emit(false);
  }
}
