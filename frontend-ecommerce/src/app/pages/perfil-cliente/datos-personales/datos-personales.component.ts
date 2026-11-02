import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { PerfilCliente } from '../../../core/models/perfil-cliente.model';
import { PerfilClienteService } from '../../../core/services/perfil-cliente.service';
import {FECHA_NACIMIENTO_MIN,errorFechaNacimiento,fechaNacimientoMax,} from '../../../shared/validators/fecha-nacimiento';

@Component({
  selector: 'app-datos-personales',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './datos-personales.component.html',
  styleUrl: './datos-personales.component.css',
})
export class DatosPersonalesComponent implements OnChanges {
  @Input() perfil: PerfilCliente | null = null;
  @Output() actualizado = new EventEmitter<PerfilCliente>();

  guardando = false;

  per_nombr1 = '';
  per_nombr2 = '';
  per_apell1 = '';
  per_apell2 = '';
  per_fechna = '';
  celular = '';
  cli_ruc = '';
  per_cedula = '';
  tipoDocumento: 'CI' | 'PAS' = 'CI';

  /** El documento solo se puede cargar si todavía no está registrado. */
  get cedulaEditable(): boolean {
    return !this.perfil?.persona?.per_cedula;
  }

  get esPasaporte(): boolean {
    return this.tipoDocumento === 'PAS';
  }

  /** Etiqueta del documento ya registrado (solo lectura). */
  get etiquetaDocumento(): string {
    return this.perfil?.persona?.tpd_codigo === 'PAS' ? 'Pasaporte' : 'Cédula';
  }

  avatarPreview: string | null = null;
  private avatarFile: File | null = null;

  constructor(
    private perfilClienteService: PerfilClienteService,
    private toastr: ToastrService,
  ) {}

  readonly fechaMin = FECHA_NACIMIENTO_MIN;
  readonly fechaMax = fechaNacimientoMax();

  /** Error en vivo debajo del campo. */
  get errorFecha(): string | null {
    return errorFechaNacimiento(this.per_fechna);
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['perfil'] && this.perfil) {
      this.per_nombr1 = this.perfil.persona.per_nombr1 ?? '';
      this.per_nombr2 = this.perfil.persona.per_nombr2 ?? '';
      this.per_apell1 = this.perfil.persona.per_apell1 ?? '';
      this.per_apell2 = this.perfil.persona.per_apell2 ?? '';
      this.per_fechna = this.perfil.persona.per_fechna ?? '';
      this.celular = this.perfil.celular ?? '';
      this.cli_ruc = this.perfil.cliente?.cli_ruc ?? '';
      this.avatarPreview = this.perfil.persona.avatar_url;
      this.per_cedula = this.perfil.persona.per_cedula ?? '';
      this.tipoDocumento = this.perfil.persona.tpd_codigo === 'PAS' ? 'PAS' : 'CI';
    }
  }

  seleccionarAvatar(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    this.avatarFile = file;
    const lector = new FileReader();
    lector.onload = () => (this.avatarPreview = lector.result as string);
    lector.readAsDataURL(file);
  }

  /** C.I.: solo números (se quitan los puntos). Pasaporte: letras y números en mayúsculas. */
  private documentoNormalizado(): string {
    return this.esPasaporte
      ? this.per_cedula.toUpperCase().replace(/[^A-Z0-9]/g, '')
      : this.per_cedula.replace(/\D/g, '');
  }

  private errorDocumento(valor: string): string | null {
    if (!valor) return null;
    if (this.esPasaporte) {
      return /^[A-Z0-9]{4,20}$/.test(valor) ? null : 'El pasaporte debe tener entre 4 y 20 letras o números.';
    }
    return /^\d{4,10}$/.test(valor) ? null : 'La cédula debe tener entre 4 y 10 números.';
  }

  guardar(): void {
    if (!this.per_nombr1 || !this.per_apell1) {
      this.toastr.warning('Completá al menos nombre y apellido.', 'Faltan datos');
      return;
    }

    if (this.errorFecha) {
      this.toastr.warning(this.errorFecha, 'Fecha de nacimiento');
      return;
    }

    const documento = this.cedulaEditable ? this.documentoNormalizado() : '';
    const errorDoc = this.cedulaEditable ? this.errorDocumento(documento) : null;
    if (errorDoc) {
      this.toastr.warning(errorDoc, this.esPasaporte ? 'Pasaporte' : 'Cédula');
      return;
    }

    const formData = new FormData();
    formData.append('per_nombr1', this.per_nombr1);
    formData.append('per_nombr2', this.per_nombr2 ?? '');
    formData.append('per_apell1', this.per_apell1);
    formData.append('per_apell2', this.per_apell2 ?? '');
    if (this.per_fechna) formData.append('per_fechna', this.per_fechna);
    if (this.celular) formData.append('celular', this.celular);
    if (this.cli_ruc) formData.append('cli_ruc', this.cli_ruc);
    if (this.avatarFile) formData.append('avatar', this.avatarFile);
    if (documento) {
      formData.append('tipo_documento', this.tipoDocumento);
      formData.append('per_cedula', documento);
    }

    this.guardando = true;
    this.perfilClienteService.actualizar(formData).subscribe({
      next: (resp) => {
        this.guardando = false;
        this.avatarFile = null;
        this.toastr.success(resp.message, 'Perfil actualizado');
        this.actualizado.emit(resp.data);
      },
      error: (err) => {
        this.guardando = false;
        // En validaciones (422) se muestra el primer error concreto, no el mensaje genérico.
        const errores = err.error?.errors;
        const primero = errores ? (Object.values(errores)[0] as string[] | undefined)?.[0] : null;
        this.toastr.error(primero || err.error?.message || 'No se pudo actualizar el perfil.', 'Error');
      },
    });
  }
}