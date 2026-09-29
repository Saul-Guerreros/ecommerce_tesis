import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { PerfilCliente } from '../../../core/models/perfil-cliente.model';
import { PerfilClienteService } from '../../../core/services/perfil-cliente.service';

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

  avatarPreview: string | null = null;
  private avatarFile: File | null = null;

  constructor(
    private perfilClienteService: PerfilClienteService,
    private toastr: ToastrService,
  ) {}

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

  guardar(): void {
    if (!this.per_nombr1 || !this.per_apell1) {
      this.toastr.warning('Completá al menos nombre y apellido.', 'Faltan datos');
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
        const mensaje = err.error?.message || 'No se pudo actualizar el perfil.';
        this.toastr.error(mensaje, 'Error');
      },
    });
  }
}