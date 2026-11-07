import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ButtonModule, CardModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { ToastrService } from 'ngx-toastr';
import { PaginaNosotrosService } from './service/pagina-nosotros.service';
import { CifraNosotros, ICONOS_NOSOTROS, PaginaNosotros, ValorNosotros } from './models/pagina-nosotros.model';

@Component({
  selector: 'app-pagina-nosotros',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, CardModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './pagina-nosotros.component.html',
})
export class PaginaNosotrosComponent implements OnInit, OnDestroy {
  // Mismos límites que PaginaNosotros (backend)
  readonly MAX_VALORES = 6;
  readonly MAX_CIFRAS = 4;
  readonly MAX_MB = 3;
  readonly TIPOS_IMAGEN = ['image/jpeg', 'image/png', 'image/webp'];
  readonly iconos = ICONOS_NOSOTROS;

  form: FormGroup;
  cargando = true;
  guardando = false;
  subiendoImagen = false;
  modalEliminarImagen = false;

  imagenUrl: string | null = null;
  vistaPrevia: string | null = null;
  archivo: File | null = null;
  actualizado: string | null = null;

  constructor(
    private fb: FormBuilder,
    private service: PaginaNosotrosService,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      pno_titulo: ['', [Validators.required, Validators.maxLength(120)]],
      pno_subtitulo: ['', Validators.maxLength(255)],
      pno_historia: ['', Validators.maxLength(5000)],
      pno_mision: ['', Validators.maxLength(600)],
      pno_vision: ['', Validators.maxLength(600)],
      pno_valores: this.fb.array([]),
      pno_cifras: this.fb.array([]),
      pno_publicado: [false],
    });
  }

  get valores(): FormArray {
    return this.form.get('pno_valores') as FormArray;
  }

  get cifras(): FormArray {
    return this.form.get('pno_cifras') as FormArray;
  }

  ngOnInit(): void {
    this.cargar();
  }

  ngOnDestroy(): void {
    this.liberarVistaPrevia();
  }

  // ---------- Carga ----------

  private cargar(): void {
    this.cargando = true;
    this.service.get().subscribe({
      next: (data) => {
        this.aplicar(data);
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar la página', 'Error');
        this.cargando = false;
      },
    });
  }

  private aplicar(data: PaginaNosotros): void {
    this.form.patchValue({
      pno_titulo: data.pno_titulo,
      pno_subtitulo: data.pno_subtitulo,
      pno_historia: data.pno_historia,
      pno_mision: data.pno_mision,
      pno_vision: data.pno_vision,
      pno_publicado: data.pno_publicado,
    });
    this.valores.clear();
    (data.pno_valores ?? []).forEach((v) => this.valores.push(this.crearValor(v)));
    this.cifras.clear();
    (data.pno_cifras ?? []).forEach((c) => this.cifras.push(this.crearCifra(c)));

    this.imagenUrl = data.pno_imagen_url;
    this.actualizado = data.pno_actualizado;
    this.form.markAsPristine();
  }

  // ---------- Valores y cifras (listas dinámicas) ----------

  private crearValor(v?: ValorNosotros): FormGroup {
    return this.fb.group({
      icono: [v?.icono ?? 'star', Validators.required],
      titulo: [v?.titulo ?? '', [Validators.required, Validators.maxLength(60)]],
      descripcion: [v?.descripcion ?? '', Validators.maxLength(200)],
    });
  }

  private crearCifra(c?: CifraNosotros): FormGroup {
    return this.fb.group({
      valor: [c?.valor ?? '', [Validators.required, Validators.maxLength(20)]],
      etiqueta: [c?.etiqueta ?? '', [Validators.required, Validators.maxLength(60)]],
    });
  }

  agregarValor(): void {
    if (this.valores.length >= this.MAX_VALORES) return;
    this.valores.push(this.crearValor());
    this.form.markAsDirty();
  }

  quitarValor(i: number): void {
    this.valores.removeAt(i);
    this.form.markAsDirty();
  }

  agregarCifra(): void {
    if (this.cifras.length >= this.MAX_CIFRAS) return;
    this.cifras.push(this.crearCifra());
    this.form.markAsDirty();
  }

  quitarCifra(i: number): void {
    this.cifras.removeAt(i);
    this.form.markAsDirty();
  }

  /** Sube o baja un ítem para cambiar el orden en que se muestra en la tienda. */
  mover(lista: FormArray, i: number, delta: number): void {
    const destino = i + delta;
    if (destino < 0 || destino >= lista.length) return;
    const control = lista.at(i);
    lista.removeAt(i);
    lista.insert(destino, control);
    this.form.markAsDirty();
  }

  // ---------- Validación en pantalla ----------

  invalido(ruta: string): boolean {
    const c = this.form.get(ruta);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(ruta: string): string | null {
    return this.form.get(ruta)?.errors?.['backend'] ?? null;
  }

  largo(ruta: string): number {
    return (this.form.get(ruta)?.value ?? '').length;
  }

  // ---------- Guardar texto ----------

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.toastr.warning('Revisá los campos marcados', 'Validación');
      return;
    }

    this.guardando = true;
    this.service.update(this.form.getRawValue()).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Página actualizada', 'Éxito');
        this.aplicar(res.data);
        this.guardando = false;
      },
      error: (err) => {
        this.guardando = false;
        if (err.status === 422 && err.error?.errors) {
          // Las claves del backend ("pno_valores.0.titulo") son rutas válidas para form.get()
          Object.entries(err.error.errors as Record<string, string[]>).forEach(([campo, mensajes]) => {
            const control = this.form.get(campo);
            control?.setErrors({ backend: mensajes[0] });
            control?.markAsTouched();
          });
          this.toastr.error('Revisá los datos ingresados', 'Validación');
          return;
        }
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar', 'Error');
      },
    });
  }

  // ---------- Imagen ----------

  seleccionarImagen(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = ''; // permite volver a elegir el mismo archivo
    if (!file) return;

    // Validación rápida en el cliente; la definitiva la hace el backend.
    if (!this.TIPOS_IMAGEN.includes(file.type)) {
      this.toastr.warning('Formato no permitido. Usá JPG, PNG o WEBP.', 'Imagen');
      return;
    }
    if (file.size > this.MAX_MB * 1024 * 1024) {
      this.toastr.warning(`La imagen no puede superar ${this.MAX_MB} MB.`, 'Imagen');
      return;
    }

    this.liberarVistaPrevia();
    this.archivo = file;
    this.vistaPrevia = URL.createObjectURL(file);
  }

  cancelarImagen(): void {
    this.liberarVistaPrevia();
  }

  subirImagen(): void {
    if (!this.archivo) return;
    this.subiendoImagen = true;
    this.service.subirImagen(this.archivo).subscribe({
      next: (res) => {
        this.imagenUrl = res.data.pno_imagen_url;
        this.liberarVistaPrevia();
        this.subiendoImagen = false;
        this.toastr.success(res?.message ?? 'Imagen actualizada', 'Éxito');
      },
      error: (err) => {
        this.subiendoImagen = false;
        const msg = err?.error?.errors?.imagen?.[0] ?? err?.error?.message ?? 'No se pudo subir la imagen';
        this.toastr.error(msg, 'Error');
      },
    });
  }

  confirmarEliminarImagen(): void {
    this.subiendoImagen = true;
    this.service.eliminarImagen().subscribe({
      next: (res) => {
        this.imagenUrl = null;
        this.subiendoImagen = false;
        this.modalEliminarImagen = false;
        this.toastr.success(res?.message ?? 'Imagen eliminada', 'Éxito');
      },
      error: (err) => {
        this.subiendoImagen = false;
        this.modalEliminarImagen = false;
        this.toastr.error(err?.error?.message ?? 'No se pudo eliminar la imagen', 'Error');
      },
    });
  }

  private liberarVistaPrevia(): void {
    if (this.vistaPrevia) URL.revokeObjectURL(this.vistaPrevia);
    this.vistaPrevia = null;
    this.archivo = null;
  }
}