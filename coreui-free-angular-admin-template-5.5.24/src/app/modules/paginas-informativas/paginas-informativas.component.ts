import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ButtonModule, CardModule, FormModule, SpinnerModule } from '@coreui/angular';
import { ToastrService } from 'ngx-toastr';
import { QuillModule } from 'ngx-quill';
import { PaginasInformativasService } from './service/paginas-informativas.service';

@Component({
  selector: 'app-paginas-informativas',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, CardModule, FormModule, SpinnerModule, QuillModule],
  templateUrl: './paginas-informativas.component.html',
})
export class PaginasInformativasComponent implements OnInit {
  form: FormGroup;
  cargando = true;
  guardando = false;
  errorCarga = false;
  paginaId: number | null = null;
  tituloCard = '';
  private slugActual = '';

  // Barra de herramientas del editor
  editorModulos = {
    toolbar: [
      [{ header: [1, 2, 3, false] }],           // Título / Subtítulo / Normal
      ['bold', 'italic', 'underline'],           // Negrita, cursiva, subrayado
      [{ list: 'ordered' }, { list: 'bullet' }], // Numeración y viñetas
      ['link'],
      ['clean'],                                 // Borrar formato
    ],
  };

  constructor(
    private fb: FormBuilder,
    private service: PaginasInformativasService,
    private toastr: ToastrService,
    private route: ActivatedRoute,
  ) {
    this.form = this.fb.group({
      pgi_titulo: ['', [Validators.required, Validators.maxLength(150)]],
      pgi_contenido: ['', Validators.required],
    });
  }

  ngOnInit(): void {
    this.route.paramMap.subscribe((params) => {
      this.slugActual = params.get('slug') ?? '';
      this.cargar();
    });
  }

  /** Carga la página por slug. Público para poder reintentar desde la vista. */
  cargar(): void {
    this.cargando = true;
    this.errorCarga = false;
    this.paginaId = null;
    this.tituloCard = '';

    this.service.getPorSlug(this.slugActual).subscribe({
      next: (pagina) => {
        this.paginaId = pagina.pgi_id;
        this.tituloCard = pagina.pgi_titulo;
        this.form.patchValue(pagina);
        this.form.markAsPristine();
        this.cargando = false;
      },
      error: (err) => {
        this.errorCarga = true;
        this.cargando = false;
        this.toastr.error(err?.error?.message ?? err?.message ?? 'No se pudo cargar la página', 'Error');
      },
    });
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  guardar(): void {
    if (!this.paginaId) {
      this.toastr.warning('La página aún no está cargada. Recargá la sección e intentá de nuevo.', 'Atención');
      return;
    }

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    this.service.update(this.paginaId, this.form.value).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Página actualizada.', 'Éxito');
        this.form.markAsPristine();
        this.guardando = false;
      },
      error: (err) => {
        this.guardando = false;
        const detalle = err?.error?.errors
          ? Object.values(err.error.errors as Record<string, string[]>).flat().join(' ')
          : err?.error?.message;
        this.toastr.error(detalle ?? `No se pudo guardar (HTTP ${err?.status ?? '?'})`, 'Error');
      },
    });
  }
}