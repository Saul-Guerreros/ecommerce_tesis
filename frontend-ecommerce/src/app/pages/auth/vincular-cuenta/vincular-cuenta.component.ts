import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { AuthService } from '../service/auth.service';

/**
 * Crear la cuenta de la tienda sobre la ficha que la sucursal ya tiene del cliente.
 * Llega desde el QR/enlace de la constancia (/vincular-cuenta?codigo=XXXX-XXXX).
 */
@Component({
  selector: 'app-vincular-cuenta',
  standalone: true,
  imports: [FormsModule, RouterModule],
  templateUrl: './vincular-cuenta.component.html',
  styleUrl: '../register/register.component.css',
})
export class VincularCuentaComponent implements OnInit {
  tipoDocumento: 'CI' | 'PAS' = 'CI';
  cedula = '';
  codigo = '';
  email = '';
  password = '';
  password_confirmation = '';
  terminos = false;

  procesando = false;
  private readonly regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  constructor(
    private authService: AuthService,
    private router: Router,
    private toastr: ToastrService,
    private route: ActivatedRoute,
  ) {}

  /** El QR trae el código ya cargado: el cliente solo agrega su documento. */
  ngOnInit(): void {
    const codigo = this.route.snapshot.queryParamMap.get('codigo');
    if (codigo) {
      this.codigo = codigo.trim().toUpperCase().slice(0, 20);
    }
  }

  onCodigo(valor: string): void {
    this.codigo = (valor || '').toUpperCase();
  }

  vincular(): void {
    if (this.procesando) return;

    const error = this.validar();
    if (error) {
      this.toastr.warning(error, 'Revisá los datos');
      return;
    }

    this.procesando = true;
    this.authService
      .vincularCuenta({
        tipo_documento: this.tipoDocumento,
        cedula: this.cedula.trim(),
        codigo: this.codigo.trim(),
        email: this.email.trim(),
        password: this.password,
      })
      .subscribe({
        next: () => {
          this.procesando = false;
          this.toastr.success('Te enviamos un correo para activar tu cuenta.', 'Cuenta creada');
          this.router.navigateByUrl('/login');
        },
        error: (err: any) => {
          this.procesando = false;
          this.toastr.error(this.mensajeError(err), 'No se pudo vincular');
        },
      });
  }

  private validar(): string | null {
    if (!this.cedula.trim()) return 'Ingresá tu número de documento.';
    if (!this.codigo.trim()) return 'Ingresá el código de vinculación de tu constancia.';
    if (!this.regexEmail.test(this.email.trim())) return 'Ingresá un correo electrónico válido.';
    if (this.password.length < 8) return 'La contraseña debe tener al menos 8 caracteres.';
    if (this.password !== this.password_confirmation) return 'Las contraseñas no coinciden.';
    if (!this.terminos) return 'Debés aceptar los Términos y Condiciones y la Política de Privacidad.';
    return null;
  }

  /** Primer mensaje de validación de Laravel (422), o uno genérico. */
  private mensajeError(err: any): string {
    const errores = err?.error?.errors;
    if (errores && typeof errores === 'object') {
      const primero = Object.values(errores)[0] as string[] | undefined;
      if (primero?.length) return primero[0];
    }
    if (err?.status === 429) return 'Demasiados intentos. Esperá un minuto e intentá de nuevo.';
    return err?.error?.message || 'No se pudo completar la vinculación. Revisá los datos.';
  }
}