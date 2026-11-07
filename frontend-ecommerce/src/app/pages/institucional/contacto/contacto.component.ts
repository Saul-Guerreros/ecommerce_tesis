import { Component, OnInit } from '@angular/core';
import { RouterModule } from '@angular/router';
import { DomSanitizer, Meta, SafeResourceUrl, Title } from '@angular/platform-browser';
import { PaginaInstitucionalService } from '../../../core/services/pagina-institucional.service';
import { DatosContacto, RedSocial } from '../../../core/models/pagina-institucional.model';

@Component({
  selector: 'app-contacto',
  standalone: true,
  imports: [RouterModule],
  templateUrl: './contacto.component.html',
  styleUrl: './contacto.component.css',
})
export class ContactoComponent implements OnInit {
  contacto: DatosContacto | null = null;
  cargando = true;

  // Se calculan una vez al cargar (no en getters) para no recrear el iframe.
  whatsapp: string | null = null;
  telefono: string | null = null;
  email: string | null = null;
  comoLlegar: string | null = null;
  mapaUrl: SafeResourceUrl | null = null;
  redes: RedSocial[] = [];

  constructor(
    private service: PaginaInstitucionalService,
    private sanitizer: DomSanitizer,
    private title: Title,
    private meta: Meta,
  ) {}

  ngOnInit(): void {
    this.service.obtenerContacto().subscribe((c) => {
      this.contacto = c;
      this.cargando = false;
      if (!c) return;

      this.whatsapp = this.service.enlaceWhatsapp(c);
      this.telefono = this.service.enlaceTelefono(c);
      this.email = this.service.enlaceEmail(c);
      this.comoLlegar = this.service.enlaceComoLlegar(c);
      this.redes = this.service.redesSociales(c);
      this.mapaUrl = this.construirMapa(c);

      this.title.setTitle(`${c.dco_titulo} | Z Informática`);
      if (c.dco_intro) this.meta.updateTag({ name: 'description', content: c.dco_intro });
    });
  }

  /**
   * bypassSecurityTrustResourceUrl es seguro acá: la URL tiene host fijo y
   * solo lleva números validados (lat, lng, zoom), nunca texto del admin.
   */
  private construirMapa(c: DatosContacto): SafeResourceUrl | null {
    if (!this.service.coordenadasValidas(c)) return null;
    const lat = Number(c.dco_mapa_lat);
    const lng = Number(c.dco_mapa_lng);
    const zoom = Math.min(20, Math.max(10, Math.round(Number(c.dco_mapa_zoom) || 16)));
    return this.sanitizer.bypassSecurityTrustResourceUrl(
      `https://maps.google.com/maps?q=${lat},${lng}&z=${zoom}&output=embed`,
    );
  }
}