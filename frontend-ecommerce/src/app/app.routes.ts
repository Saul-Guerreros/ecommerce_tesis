import { Routes } from '@angular/router';
import { HomeComponent } from './pages/home/home.component';
import { LoginComponent } from './pages/auth/login/login.component';
import { RegisterComponent } from './pages/auth/register/register.component';
import { ForgotPasswordComponent } from './pages/auth/forgot-password/forgot-password.component';
import { ProductoDetalleComponent } from './pages/producto-detalle/producto-detalle.component';

export const routes: Routes = [
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
        // Detalle público de producto (VitrinaController::detalle vía slug).
        path: 'producto/:slug',
        component: ProductoDetalleComponent
    }
];
