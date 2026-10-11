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

import { PasoConfirmacionComponent } from './pages/checkout/paso-confirmacion/paso-confirmacion.component';

import { PasoFacturacionComponent } from './pages/checkout/paso-facturacion/paso-facturacion.component';

// --- nuevo ---
import { CheckoutComponent } from './pages/checkout/checkout.component';
import { PedidoConfirmadoComponent } from './pages/pedido-confirmado/pedido-confirmado.component';
import { PasoEntregaComponent } from './pages/checkout/paso-entrega/paso-entrega.component';



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
];