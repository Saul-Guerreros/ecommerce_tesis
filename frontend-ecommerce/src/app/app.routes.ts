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

export const routes: Routes = [
    {
        path: '',
        component: HomeComponent
    },

    {
        //canActivate: [authGuard],
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
        // Detalle público de producto (VitrinaController::detalle vía slug).
        path: 'producto/:slug',
        component: ProductoDetalleComponent
    },
    {
        // Área privada del cliente: datos personales, avatar, direcciones y contraseña.
        path: 'perfil-del-cliente',
        component: PerfilClienteComponent,
        canActivate: [suthGuard]
    },
    {
        // Catálogo con filtros avanzados; el buscador del header ya apunta acá.
        path: 'productos-busqueda',
        component: ProductosBusquedaComponent
    },
	{
    // Pública: el carrito funciona con invitados (localStorage) y con
    // usuarios logueados (BD). El login solo se exige en /proceso-de-pago.
    // El path debe coincidir con los routerLink ya presentes en
    // header.component.html y footer.component.html.
    path: 'carrito-de-compra',
    component: CarritoComponent
    },
   ];

