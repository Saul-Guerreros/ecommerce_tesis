import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { AuthService } from '../service/auth.service';
import { ToastrService } from 'ngx-toastr';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [FormsModule,RouterModule],
  templateUrl: './register.component.html',
  styleUrl: './register.component.css'
})
export class RegisterComponent {
  // Campos alineados a AuthController::register() en el backend
  // (persona + cliente + usuario, ver modulo de Autenticacion y Perfil).
  nombre1:string = '';
  nombre2:string = '';
  apellido1:string = '';
  apellido2:string = '';
  celular:string = '';
  email:string = '';
  password:string = '';
  password_confirmation:string = '';
  terminos:boolean = false;

  constructor(
    private authService: AuthService,
    private router: Router,
    private toastr: ToastrService
  ){
  }

  register(){
    if (!this.nombre1 || !this.apellido1 || !this.celular || !this.email || !this.password) {
      this.toastr.error("validacion","Necesitas ingresar nombre, apellido, celular, email y contraseña");
      return;
    }
    if (this.password !== this.password_confirmation) {
      this.toastr.error("validacion","Las contraseñas no coinciden");
      return;
    }
    if (!this.terminos) {
      this.toastr.error("validacion","Debes aceptar los Términos y Condiciones y la Política de Privacidad");
      return;
    }
    let data = {
      nombre1: this.nombre1,
      nombre2: this.nombre2 || null,
      apellido1: this.apellido1,
      apellido2: this.apellido2 || null,
      celular: this.celular,
      email: this.email,
      password: this.password,
    }
    this.authService.register(data).subscribe({
      next: (resp:any) => {
        console.log(resp);
        this.toastr.success("Exito","Ingrese a tu correo para poder completar tu registro");
        setTimeout(() => {
          this.router.navigateByUrl("/login");
        }, 500);
      },
      error: (err:any) => {
        console.log(err);
        const errores = err?.error ? this.parseErrores(err.error) : null;
        this.toastr.error("validacion", errores || "No se pudo completar el registro. Revisa los datos.");
      }
    });
  }

  private parseErrores(errorBody: any): string | null {
    try {
      const errores = typeof errorBody === 'string' ? JSON.parse(errorBody) : errorBody;
      const primerCampo = Object.keys(errores)[0];
      if (primerCampo && Array.isArray(errores[primerCampo])) {
        return errores[primerCampo][0];
      }
    } catch {
      // ignorar, se usa el mensaje generico
    }
    return null;
  }
}