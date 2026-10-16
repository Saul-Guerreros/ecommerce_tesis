import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';

/**
 * Pantalla "Solicitud enviada correctamente". Mismo patrón que
 * PedidoConfirmadoComponent: vive en su propia ruta y toma el número de
 * la URL, así que NO depende del borrador (ya borrado por el backend al
 * generar) -- el cliente puede volver con el botón del navegador sin
 * encontrarse una página muerta que lo redirija.
 */
@Component({
  selector: 'app-solicitud-enviada',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './solicitud-enviada.component.html',
  styleUrl: './solicitud-enviada.component.css',
})
export class SolicitudEnviadaComponent implements OnInit {
  numero = '';

  constructor(
    private route: ActivatedRoute,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.numero = this.route.snapshot.paramMap.get('numero') ?? '';
  }

  volverATienda(): void {
    this.router.navigate(['/']);
  }

  volverAlCarrito(): void {
    this.router.navigate(['/carrito-de-compra']);
  }

  verMisSolicitudes(): void {
    this.router.navigate(['/mis-solicitudes-credito']);
  }
}