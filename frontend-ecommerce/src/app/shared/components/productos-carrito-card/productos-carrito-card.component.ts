import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, Input } from '@angular/core';
import { URL_BACKEND } from '../../../config/config';
import { ProductoResumenCardItem } from '../../../core/models/resumen-compra.model';

/**
 * Tarjeta "Productos en Carrito": resumen de lo que se está comprando.
 *
 * Empieza siempre cerrada (sólo el encabezado con la cantidad). Al abrirla,
 * el listado se dibuja como una superposición flotante encima del resto de
 * la página (position: absolute, ver .pcc-panel en el CSS) en vez de
 * empujar el contenido hacia abajo: con un carrito largo, la página no
 * tiene por qué estirarse sólo por esta tarjeta.
 */
@Component({
  selector: 'app-productos-carrito-card',
  standalone: true,
  imports: [CommonModule, CurrencyPipe],
  templateUrl: './productos-carrito-card.component.html',
  styleUrl: './productos-carrito-card.component.css',
})
export class ProductosCarritoCardComponent {
  @Input() items: ProductoResumenCardItem[] = [];

  abierto = false;

  /**
   * URLs ya resueltas que fallaron al cargar. Se guardan resueltas (no el
   * valor crudo de item.imagen) porque es contra esa URL final que el
   * <img> realmente intenta cargar.
   */
  private imagenesRotas = new Set<string>();

  toggle(): void {
    this.abierto = !this.abierto;
  }

  cerrar(): void {
    this.abierto = false;
  }

  /**
   * La API devuelve la ruta de la imagen relativa, no absoluta — mismo
   * criterio que ya usan carrito.component.ts, producto-detalle y home.
   * Sin este paso, el <img> intenta cargar algo como "productos/foto.jpg"
   * contra el propio dominio de Angular en vez de contra la API, y nunca
   * encuentra el archivo aunque exista en el catálogo.
   */
  resolverImagen(ruta: string | null): string | null {
    if (!ruta) return null;
    if (ruta.startsWith('http')) return ruta;
    return URL_BACKEND.replace(/\/$/, '') + '/' + ruta.replace(/^\//, '');
  }

  muestraImagen(item: ProductoResumenCardItem): boolean {
    const url = this.resolverImagen(item.imagen);
    return !!url && !this.imagenesRotas.has(url);
  }

  onImgError(item: ProductoResumenCardItem): void {
    const url = this.resolverImagen(item.imagen);
    if (url) this.imagenesRotas.add(url);
  }
}
