import { Routes } from '@angular/router';
import { HomeComponent } from './pages/home/home.component';
import { LoginComponent } from './pages/auth/login/login.component';
import { RegisterComponent } from './pages/auth/register/register.component';
import { ForgotPasswordComponent } from './pages/auth/forgot-password/forgot-password.component';
import { ProductoDetalleComponent } from './pages/producto-detalle/producto-detalle.component';
import { PerfilClienteComponent } from './pages/perfil-cliente/perfil-cliente.component';
import { suthGuard } from './pages/auth/service/suth.guard';
import { ProductosBusquedaComponent } from './pages/productos-busqueda/productos-busqueda.component';
import { authGuard } from './pages/auth/service/auth.guard';
import { CarritoComponent } from './pages/carrito/carrito.component';
import { ComparadorComponent } from './pages/comparador/comparador.component';
import { PasoConfirmacionComponent } from './pages/checkout/paso-confirmacion/paso-confirmacion.component';

import { PasoFacturacionComponent } from './pages/checkout/paso-facturacion/paso-facturacion.component';

// --- nuevo ---
import { CheckoutComponent } from './pages/checkout/checkout.component';
import { PedidoConfirmadoComponent } from './pages/pedido-confirmado/pedido-confirmado.component';
import { PasoEntregaComponent } from './pages/checkout/paso-entrega/paso-entrega.component';

// --- Solicitud de Crédito (continúa el flujo del Carrito a Crédito) ---
import { SolicitudCreditoComponent } from './pages/solicitud-credito/solicitud-credito.component';
import { carritoCreditoNoVacioGuard } from './pages/solicitud-credito/guard/carrito-credito-no-vacio.guard';
import { PasoPersonalComponent } from './pages/solicitud-credito/paso-personal/paso-personal.component';
import { PasoLaboralComponent } from './pages/solicitud-credito/paso-laboral/paso-laboral.component';
import { PasoReferenciasComponent } from './pages/solicitud-credito/paso-referencias/paso-referencias.component';
import { PasoConfirmacionCreditoComponent } from './pages/solicitud-credito/paso-confirmacion-credito/paso-confirmacion-credito.component';
import { SolicitudEnviadaComponent } from './pages/solicitud-enviada/solicitud-enviada.component';
import { MisSolicitudesCreditoComponent } from './pages/mis-solicitudes-credito/mis-solicitudes-credito.component';



export const routes: Routes = [
    {
        path: 'terminos-y-condiciones',
        loadComponent: () =>
            import('./pages/legal/terminos/terminos.component').then((m) => m.TerminosComponent),
        title: 'Términos y Condiciones | Z Informática',
    },
    {
        path: 'politica-de-privacidad',
        loadComponent: () =>
            import('./pages/legal/privacidad/privacidad.component').then((m) => m.PrivacidadComponent),
        title: 'Política de Privacidad | Z Informática',
    },
    {
        path: '',
        component: HomeComponent
    },
    {
        path: 'login',
        component: LoginComponent
    },
    {
        path: 'register',
        component: RegisterComponent
    },
    {
        path: 'cambiar-credenciales',
        component: ForgotPasswordComponent
    },
    {
        path: 'producto/:slug',
        component: ProductoDetalleComponent
    },
    {
        path: 'perfil-del-cliente',
        component: PerfilClienteComponent,
        canActivate: [suthGuard]
    },
    {
        path: 'productos-busqueda',
        component: ProductosBusquedaComponent
    },
    {
        path: 'carrito-de-compra',
        component: CarritoComponent
    },
    // --- Favoritos: pública (los invitados guardan en su navegador). ---
    {
        path: 'favoritos',
        loadComponent: () =>
            import('./pages/favoritos/favoritos.component').then((m) => m.FavoritosComponent),
        title: 'Mis favoritos | Z Informática',
    },
	// --- nuevo: Comparador de productos (público, sin sesión) ---
    {
        path: 'comparar-productos',
        component: ComparadorComponent,
        title: 'Comparar productos | Z Informática',
    },
    // Compatibilidad: los enlaces viejos (/compare-product) siguen funcionando.
    { path: 'compare-product', redirectTo: 'comparar-productos', pathMatch: 'full' },
    // --- nuevo: Checkout por pasos. Exige sesión. ---
    {
        path: 'proceso-de-pago',
        component: CheckoutComponent,
        canActivate: [suthGuard],
        canActivateChild: [suthGuard],
        children: [
        { path: '', pathMatch: 'full', redirectTo: 'entrega' },
        { path: 'entrega', component: PasoEntregaComponent },
        { path: 'facturacion', component: PasoFacturacionComponent },
        { path: 'confirmacion', component: PasoConfirmacionComponent },  // <-- movido acá
        ],
    },
    {
        path: 'pedido-confirmado/:numero',
        component: PedidoConfirmadoComponent,
        canActivate: [suthGuard],
    },
    // --- Solicitud de Crédito: wizard de 4 pasos. Exige sesión y que haya
    //     al menos un producto en car_modpag = 'credito' en el carrito. ---
    {
        path: 'solicitar-credito',
        component: SolicitudCreditoComponent,
        canActivate: [suthGuard, carritoCreditoNoVacioGuard],
        canActivateChild: [suthGuard],
        children: [
        { path: '', pathMatch: 'full', redirectTo: 'personal' },
        { path: 'personal', component: PasoPersonalComponent },
        { path: 'laboral', component: PasoLaboralComponent },
        { path: 'referencias', component: PasoReferenciasComponent },
        { path: 'confirmacion', component: PasoConfirmacionCreditoComponent },
        ],
    },
    {
        path: 'solicitud-enviada/:numero',
        component: SolicitudEnviadaComponent,
        canActivate: [suthGuard],
    },
    {
        path: 'mis-solicitudes-credito',
        component: MisSolicitudesCreditoComponent,
        canActivate: [suthGuard],
    },
];