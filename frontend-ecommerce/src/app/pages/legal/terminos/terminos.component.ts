import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { PaginaInformativaService } from '../../../core/services/pagina-informativa.service';

@Component({
  selector: 'app-terminos',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './terminos.component.html',
})
export class TerminosComponent implements OnInit {
  contenido: SafeHtml = '';
  cargando = true;

  constructor(
    private service: PaginaInformativaService,
    private sanitizer: DomSanitizer,
  ) {}

  ngOnInit(): void {
    this.service.obtener('terminos-y-condiciones').subscribe({
      next: (pagina) => {
        this.contenido = this.sanitizer.bypassSecurityTrustHtml(pagina.pgi_contenido);
        this.cargando = false;
      },
      error: () => (this.cargando = false),
    });
  }
}