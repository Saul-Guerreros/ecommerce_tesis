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
  name:string = '';
  surname:string = '';
  email:string = '';
  password:string = '';
  phone:string = '';

  constructor(
    private authService: AuthService,
    private router: Router,
    private toastr: ToastrService
  ){
  }

  register(){
    if (!this.name || !this.surname || !this.email || !this.password || !this.phone ) {
      this.toastr.error("validacion","Necesitas ingresar todos los campos");
      return;
    }
    let data = {
      name: this.name,
      surname: this.surname,
      email: this.email,
      password: this.password,
      phone: this.phone,
    }
    this.authService.register(data).subscribe((resp:any) => {
        console.log(resp);
        this.toastr.success("Exitos","Ingrese a tu correo para poder completar tu registro");
        setTimeout(() => {
          this.router.navigateByUrl("/login");
        }, 500);
    });
  }
}
