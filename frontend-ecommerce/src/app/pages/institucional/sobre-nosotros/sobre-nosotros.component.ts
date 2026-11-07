import { Component, OnInit } from '@angular/core';
import { RouterModule } from '@angular/router';
import { Meta, Title } from '@angular/platform-browser';
import { PaginaInstitucionalService } from '../../../core/services/pagina-institucional.service';
import { PaginaNosotros } from '../../../core/models/pagina-institucional.model';
import { URL_BACKEND } from '../../../config/config';

@Component({
  selector: 'app-sobre-nosotros',
  standalone: true,
  imports: [RouterModule],
  templateUrl: './sobre-nosotros.component.html',
  styleUrl: './sobre-nosotros.component.css',
})
export class SobreNosotrosComponent implements OnInit {
  pagina: PaginaNosotros | null = null;
  parrafos: string[] = [];
  cargando = true;
  noDisponible = false;

  constructor(
    private service: PaginaInstitucionalService,
    private title: Title,
    private meta: Meta,
  ) {}

  ngOnInit(): void {
    this.service.obtenerNosotros().subscribe({
      next: (pagina) => {
         // Se rearma con URL_BACKEND (igual que producto-detalle) para no depender de APP_URL.
        this.pagina = { ...pagina, pno_imagen_url: this.resolverImagen(pagina.pno_imagen_url) };
        // La historia es texto plano: una línea en blanco separa párrafos.
        this.parrafos = (pagina.pno_historia ?? '')
          .split(/\n\s*\n/)
          .map((p) => p.trim())
          .filter(Boolean);
        this.title.setTitle(`${pagina.pno_titulo} | Z Informática`);
        if (pagina.pno_subtitulo) {
          this.meta.updateTag({ name: 'description', content: pagina.pno_subtitulo });
        }
        this.cargando = false;
      },
      error: () => {
        this.noDisponible = true;
        this.cargando = false;
      },
    });
  }
  
  private resolverImagen(url: string | null): string | null {
    if (!url) return null;
    const indice = url.indexOf('storage/');
    if (indice === -1) return url;
    return `${URL_BACKEND.replace(/\/$/, '')}/${url.substring(indice)}`;
  }
}