import { registerLocaleData } from '@angular/common';
import { ApplicationConfig, DEFAULT_CURRENCY_CODE, LOCALE_ID } from '@angular/core';
import { provideRouter } from '@angular/router';

import { provideHttpClient, withFetch, withInterceptors } from '@angular/common/http';
import { provideClientHydration } from '@angular/platform-browser';
import { provideAnimations } from '@angular/platform-browser/animations';
import { routes } from './app.routes';
import { provideToastr } from 'ngx-toastr';
import { authInterceptor } from './pages/auth/service/auth.interceptor';

import localeEsPY from '@angular/common/locales/es-PY';

import { PermisionAuth } from './pages/auth/service/auth.guard';
import { CookieService } from 'ngx-cookie-service';

registerLocaleData(localeEsPY, 'es-PY');
export const appConfig: ApplicationConfig = {
 providers: [
    { provide: LOCALE_ID, useValue: 'es-PY' },
    // Moneda por defecto del pipe `currency` (sin esto, `| currency` a secas
    // mostraría USD y el guaraní no tendría símbolo Gs.).
    { provide: DEFAULT_CURRENCY_CODE, useValue: 'PYG' },
    provideRouter(routes),
    provideAnimations(),
    provideToastr(),
    provideHttpClient(withFetch(), withInterceptors([authInterceptor])), provideClientHydration(), provideClientHydration(), CookieService,PermisionAuth]
};