import { Routes } from '@angular/router';
import { HomeComponent } from './pages/home/home.component';
import { LoginComponent } from './pages/auth/login/login.component';
import { RegisterComponent } from './pages/auth/register/register.component';
import { ForgotPasswordComponent } from './pages/auth/forgot-password/forgot-password.component';
import { ProductoDetalleComponent } from './pages/producto-detalle/producto-detalle.component';
import { PerfilClienteComponent } from './pages/perfil-cliente/perfil-cliente.component';
import { suthGuard } from './pages/auth/service/suth.guard';
import { ProductosBusquedaComponent } from './pages/productos-busqueda/productos-busqueda.component';
import { CarritoComponent } from './pages/carrito/carrito.component';
import { ComparadorComponent } from './pages/comparador/comparador.component';

// --- Checkout ---
import { CheckoutComponent } from './pages/checkout/checkout.component';
import { PasoEntregaComponent } from './pages/checkout/paso-entrega/paso-entrega.component';
import { PasoFacturacionComponent } from './pages/checkout/paso-facturacion/paso-facturacion.component';
import { PasoPagoComponent } from './pages/checkout/paso-pago/paso-pago.component';
import { PedidoResultadoComponent } from './pages/pedido-resultado/pedido-resultado.component';
import { MisPedidosComponent } from './pages/mis-pedidos/mis-pedidos.component';
import { PedidoDetalleComponent } from './pages/pedido-detalle/pedido-detalle.component';

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
    // --- Comparador de productos (público, sin sesión) ---
    {
        path: 'comparar-productos',
        component: ComparadorComponent,
        title: 'Comparar productos | Z Informática',
    },
    // Compatibilidad: los enlaces viejos (/compare-product) siguen funcionando.
    { path: 'compare-product', redirectTo: 'comparar-productos', pathMatch: 'full' },

    // --- Checkout por pasos. Exige sesión. ---
    //
    // El paso 3 lleva el número de pedido en la ruta porque, a diferencia
    // de los dos anteriores, ya no opera sobre un borrador: el pedido
    // existe y tiene stock reservado. Eso hace la pantalla reanudable.
    {
        path: 'proceso-de-pago',
        component: CheckoutComponent,
        canActivate: [suthGuard],
        canActivateChild: [suthGuard],
        children: [
            { path: '', pathMatch: 'full', redirectTo: 'entrega' },
            { path: 'entrega', component: PasoEntregaComponent },
            { path: 'facturacion', component: PasoFacturacionComponent },
            { path: 'pago/:numero', component: PasoPagoComponent },
        ],
    },
    // Pantalla final (los 3 casos). Fuera del wizard: ya no es un paso del
    // checkout sino el estado del pedido, y sigue siendo válida siempre.
    {
        path: 'pedido-resultado/:numero',
        component: PedidoResultadoComponent,
        canActivate: [suthGuard],
        title: 'Tu pedido | Z Informática',
    },
    // Compatibilidad con el flujo anterior.
    { path: 'pedido-confirmado/:numero', redirectTo: 'pedido-resultado/:numero' },

    // --- Mis Pedidos ---
    {
        path: 'mis-pedidos',
        component: MisPedidosComponent,
        canActivate: [suthGuard],
        title: 'Mis pedidos | Z Informática',
    },
    {
        path: 'mis-pedidos/:numero',
        component: PedidoDetalleComponent,
        canActivate: [suthGuard],
        title: 'Detalle del pedido | Z Informática',
    },

    // --- Solicitud de Crédito: wizard de 4 pasos. ---
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
	    {
        path: 'servicio-tecnico',
        loadComponent: () => import('./pages/servicio-tecnico/landing/servicio-landing.component').then(m => m.ServicioLandingComponent),
        title: 'Servicio técnico | Z Informática',
    },
    {
        path: 'servicio-tecnico/solicitar',
        loadComponent: () => import('./pages/servicio-tecnico/solicitud/solicitud-servicio.component').then(m => m.SolicitudServicioComponent),
        canActivate: [suthGuard],
        title: 'Solicitar servicio técnico | Z Informática',
    },
    {
        path: 'mis-servicios/:numero/pago',
        loadComponent: () => import('./pages/servicio-tecnico/pago/pago-servicio.component').then(m => m.PagoServicioComponent),
        canActivate: [suthGuard],
        title: 'Pagar servicio técnico | Z Informática',
    },
    {
        path: 'mis-servicios/:numero',
        loadComponent: () => import('./pages/servicio-tecnico/detalle/detalle-servicio.component').then(m => m.DetalleServicioComponent),
        canActivate: [suthGuard],
    },
];