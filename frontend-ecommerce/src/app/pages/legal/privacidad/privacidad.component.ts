import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { PaginaInformativaService } from '../../../core/services/pagina-informativa.service';

@Component({
  selector: 'app-privacidad',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './privacidad.component.html',
})
export class PrivacidadComponent implements OnInit {
  contenido: SafeHtml = '';
  cargando = true;

  constructor(
    private service: PaginaInformativaService,
    private sanitizer: DomSanitizer,
  ) {}

  ngOnInit(): void {
    this.service.obtener('politica-privacidad').subscribe({
      next: (pagina) => {
        this.contenido = this.sanitizer.bypassSecurityTrustHtml(pagina.pgi_contenido);
        this.cargando = false;
      },
      error: () => (this.cargando = false),
    });
  }
}