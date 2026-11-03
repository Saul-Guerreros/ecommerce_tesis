import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { RouterModule } from '@angular/router';
import { PaginaInformativaService } from '../../../core/services/pagina-informativa.service';

/**
 * Modal "Cómo funciona tu compra". El contenido lo edita el administrador
 * (Atención al Cliente y Legal → Condiciones de compra). Se descarga la
 * primera vez que se abre.
 */
@Component({
  selector: 'app-modal-condiciones-compra',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './modal-condiciones-compra.component.html',
  styleUrl: './modal-condiciones-compra.component.css',
})
export class ModalCondicionesCompraComponent implements OnChanges {
  @Input() visible = false;
  @Output() visibleChange = new EventEmitter<boolean>();

  titulo = 'Cómo funciona tu compra';
  contenido: SafeHtml = '';
  cargando = false;
  error = false;
  private cargado = false;

  constructor(
    private service: PaginaInformativaService,
    private sanitizer: DomSanitizer,
  ) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible']?.currentValue && !this.cargado) {
      this.cargar();
    }
  }

  cargar(): void {
    this.cargando = true;
    this.error = false;

    this.service.obtener('condiciones-compra').subscribe({
      next: (pagina) => {
        this.titulo = pagina.pgi_titulo;
        this.contenido = this.sanitizer.bypassSecurityTrustHtml(pagina.pgi_contenido);
        this.cargado = true;
        this.cargando = false;
      },
      error: () => {
        this.error = true;
        this.cargando = false;
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}