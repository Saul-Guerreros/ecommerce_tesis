import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { PaginaInformativaService } from '../../../core/services/pagina-informativa.service';

@Component({
  selector: 'app-garantia',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './garantia.component.html',
})
export class GarantiaComponent implements OnInit {
  contenido: SafeHtml = '';
  cargando = true;

  constructor(
    private service: PaginaInformativaService,
    private sanitizer: DomSanitizer,
  ) {}

  ngOnInit(): void {
    this.service.obtener('garantia').subscribe({
      next: (pagina) => {
        this.contenido = this.sanitizer.bypassSecurityTrustHtml(pagina.pgi_contenido);
        this.cargando = false;
      },
      error: () => (this.cargando = false),
    });
  }
}