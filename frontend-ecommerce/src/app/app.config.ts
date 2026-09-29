import { ApplicationConfig } from '@angular/core';
import { provideRouter } from '@angular/router';

import { provideHttpClient, withFetch, withInterceptors } from '@angular/common/http';
import { provideClientHydration } from '@angular/platform-browser';
import { provideAnimations } from '@angular/platform-browser/animations';
import { routes } from './app.routes';
import { provideToastr } from 'ngx-toastr';
import { authInterceptor } from './pages/auth/service/auth.interceptor';
import { registerLocaleData } from '@angular/common';
import localeEsPY from '@angular/common/locales/es-PY';

registerLocaleData(localeEsPY, 'es-PY');
export const appConfig: ApplicationConfig = {
 providers: [provideRouter(routes),
    provideAnimations(),
    provideToastr(),
    provideHttpClient(withFetch(), withInterceptors([authInterceptor])), provideClientHydration(), provideClientHydration()]
};
