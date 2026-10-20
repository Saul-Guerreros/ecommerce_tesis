import { DOCUMENT } from '@angular/common';
import { Component, DestroyRef, inject, OnInit } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute, NavigationEnd, Router, RouterOutlet } from '@angular/router';
import { delay, filter, map, tap } from 'rxjs/operators';

import { ColorModeService } from '@coreui/angular';
import { IconSetService } from '@coreui/icons-angular';
import { iconSubset } from './icons/icon-subset';

@Component({
    selector: 'app-root',
    template: '<router-outlet />',
    imports: [RouterOutlet]
})
export class AppComponent implements OnInit {
  title = 'CoreUI Angular Admin Template';

  readonly #destroyRef: DestroyRef = inject(DestroyRef);
  readonly #activatedRoute: ActivatedRoute = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #document = inject(DOCUMENT);
  readonly #titleService = inject(Title);

  readonly #colorModeService = inject(ColorModeService);
  readonly #iconSetService = inject(IconSetService);

  constructor() {
    this.#titleService.setTitle(this.title);
    // iconSet singleton
    this.#iconSetService.icons = { ...iconSubset };
    this.#colorModeService.localStorageItemName.set('coreui-free-angular-admin-template-theme-default');
    this.#colorModeService.eventName.set('ColorSchemeChange');
  }

  ngOnInit(): void {

    this.#router.events.pipe(
        takeUntilDestroyed(this.#destroyRef)
      ).subscribe((evt) => {
      if (!(evt instanceof NavigationEnd)) {
        return;
      }
      this.#limpiarBackdropsHuerfanos();
    });

    this.#activatedRoute.queryParams
      .pipe(
        delay(1),
        map(params => <string>params['theme']?.match(/^[A-Za-z0-9\s]+/)?.[0]),
        filter(theme => ['dark', 'light', 'auto'].includes(theme)),
        tap(theme => {
          this.#colorModeService.colorMode.set(theme);
        }),
        takeUntilDestroyed(this.#destroyRef)
      )
      .subscribe();
  }

  /**
   * CoreUI deja su backdrop (el filtro negro) en <body> cuando un <c-modal> se destruye
   * estando abierto: por ejemplo, cuando el interceptor redirige a /login por un 401
   * (token vencido) o un guard cambia la ruta con un formulario abierto.
   * Tras cada navegación se retiran los backdrops que ya no pertenecen a ningún modal
   * abierto (un <c-modal> visible lleva aria-modal="true"), y se restaura el scroll.
   */
  #limpiarBackdropsHuerfanos(): void {
    if (this.#document.querySelector('c-modal[aria-modal="true"]')) {
      return;
    }

    this.#document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());

    const body = this.#document.body;
    body.classList.remove('modal-open');
    body.style.removeProperty('overflow');
    body.style.removeProperty('padding-right');
  }
}