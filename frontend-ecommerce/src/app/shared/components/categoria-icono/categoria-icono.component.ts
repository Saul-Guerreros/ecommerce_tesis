import { ChangeDetectionStrategy, Component, Input, OnChanges, inject } from '@angular/core';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { obtenerSvgCategoria } from './categoria-iconos';

/**
 * Ilustración SVG de una categoría.
 * Uso: <app-categoria-icono [clave]="cat.cat_icono" [tamano]="72" />
 */
@Component({
  selector: 'app-categoria-icono',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `<span class="cat-icono" [style.width.px]="tamano" [style.height.px]="tamano" [innerHTML]="svg"></span>`,
  styles: [`
    :host { display: block; }
    .cat-icono { display: block; margin: 0 auto; transition: color .2s ease, transform .2s ease; }
  `],
})
export class CategoriaIconoComponent implements OnChanges {
  @Input() clave: string | null | undefined = null;
  @Input() tamano = 72;

  svg: SafeHtml = '';
  private readonly sanitizer = inject(DomSanitizer);

  ngOnChanges(): void {
    // Seguro: el SVG sale de constantes del código, nunca del usuario.
    // La clave de la BD solo se usa para buscar en el diccionario.
    this.svg = this.sanitizer.bypassSecurityTrustHtml(obtenerSvgCategoria(this.clave));
  }
}