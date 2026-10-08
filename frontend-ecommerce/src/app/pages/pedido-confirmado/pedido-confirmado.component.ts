import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';

/**
 * Pantalla de pedido confirmado.
 *
 * Vive en su propia ruta y toma el numero de pedido de la URL, por lo que
 * NO depende del borrador de checkout (que se borra al confirmar). Esto
 * la mantiene valida de forma permanente: el usuario puede ir y volver
 * con los botones del navegador sin encontrarse una pagina muerta que
 * tenga que redirigirlo.
 */
@Component({
  selector: 'app-pedido-confirmado',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './pedido-confirmado.component.html',
  styleUrl: './pedido-confirmado.component.css',
})
export class PedidoConfirmadoComponent implements OnInit {

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

  verMiPedido(): void {
    this.router.navigate(['/perfil-del-cliente']);
  }
}