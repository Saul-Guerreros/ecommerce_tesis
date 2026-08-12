import { ApplicationConfig } from '@angular/core';
import { provideRouter } from '@angular/router';

import { provideHttpClient, withFetch } from '@angular/common/http';
import { provideClientHydration } from '@angular/platform-browser';
import { provideAnimations } from '@angular/platform-browser/animations';
import { routes } from './app.routes';
import { provideToastr } from 'ngx-toastr';

export const appConfig: ApplicationConfig = {
 providers: [provideRouter(routes),
    provideAnimations(),
    provideToastr(),
    provideHttpClient(withFetch()), provideClientHydration(), provideClientHydration()]
};
