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
  paginaId: number | null = null;
  tituloCard = '';

  // 👇 ACÁ VA — barra de herramientas del editor
  editorModulos = {
    toolbar: [
      [{ header: [1, 2, 3, false] }],          // Título / Subtítulo / Normal
      ['bold', 'italic', 'underline'],          // Negrita, cursiva, subrayado
      [{ list: 'ordered' }, { list: 'bullet' }], // Numeración y viñetas
      ['link'],
      ['clean'],                                // Borrar formato
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
    this.route.paramMap.subscribe((params) => this.cargar(params.get('slug')!));
  }

  private cargar(slug: string): void {
    this.cargando = true;
    this.service.getPorSlug(slug).subscribe({
      next: (pagina) => {
        this.paginaId = pagina.pgi_id;
        this.tituloCard = pagina.pgi_titulo;
        this.form.patchValue(pagina);
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar la página', 'Error');
        this.cargando = false;
      },
    });
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  guardar(): void {
    if (this.form.invalid || !this.paginaId) {
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
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar', 'Error');
      },
    });
  }
}