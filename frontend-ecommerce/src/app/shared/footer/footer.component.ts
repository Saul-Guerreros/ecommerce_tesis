import { Component, OnInit } from '@angular/core';
import { RouterModule } from '@angular/router';
import { PaginaInstitucionalService } from '../../core/services/pagina-institucional.service';
import { DatosContacto, RedSocial } from '../../core/models/pagina-institucional.model';

@Component({
  selector: 'app-footer',
  standalone: true,
  imports: [RouterModule],
  templateUrl: './footer.component.html',
  styleUrl: './footer.component.css'
})
export class FooterComponent implements OnInit {
  contacto: DatosContacto | null = null;
  telefono: string | null = null;
  whatsapp: string | null = null;
  email: string | null = null;
  redes: RedSocial[] = [];
  readonly anio = new Date().getFullYear();

  constructor(private service: PaginaInstitucionalService) {}

  ngOnInit(): void {
    // Misma petición (compartida) que usa la página Contacto.
    this.service.obtenerContacto().subscribe((c) => {
      this.contacto = c;
      if (!c) return;
      this.telefono = this.service.enlaceTelefono(c);
      this.whatsapp = this.service.enlaceWhatsapp(c);
      this.email = this.service.enlaceEmail(c);
      this.redes = this.service.redesSociales(c);
    });
  }
}