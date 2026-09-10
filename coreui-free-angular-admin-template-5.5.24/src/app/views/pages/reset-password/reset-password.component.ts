import { Component, inject } from '@angular/core';
import { NgIf } from '@angular/common';
import { Router, RouterLink } from '@angular/router';

import {
  FormBuilder,
  ReactiveFormsModule,
  ValidationErrors,
  Validators
} from '@angular/forms';

import { IconDirective } from '@coreui/icons-angular';

import {
  ButtonDirective,
  CardBodyComponent,
  CardComponent,
  CardGroupComponent,
  ColComponent,
  ContainerComponent,
  FormControlDirective,
  FormDirective,
  InputGroupComponent,
  InputGroupTextDirective,
  RowComponent
} from '@coreui/angular';

import { AuthService } from '../../../auth/services/auth.service';

function passwordsIgualesValidator(group: any): ValidationErrors | null {
  const password = group.get('new_password')?.value;
  const confirm = group.get('confirm_password')?.value;
  return password && confirm && password !== confirm ? { passwordsNoCoinciden: true } : null;
}

@Component({
  selector: 'app-reset-password',
  templateUrl: './reset-password.component.html',
  standalone: true,
  imports: [
    ContainerComponent,
    RowComponent,
    ColComponent,
    CardGroupComponent,
    CardComponent,
    CardBodyComponent,
    FormDirective,
    InputGroupComponent,
    InputGroupTextDirective,
    IconDirective,
    FormControlDirective,
    ButtonDirective,
    NgIf,
    ReactiveFormsModule,
    RouterLink
  ]
})
export class ResetPasswordComponent {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private router = inject(Router);

  loading = false;
  errorMsg: string | null = null;
  exito = false;

  form = this.fb.group(
    {
      code: ['', [Validators.required]],
      new_password: ['', [Validators.required, Validators.minLength(8)]],
      confirm_password: ['', [Validators.required]]
    },
    { validators: passwordsIgualesValidator }
  );

  onSubmit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.loading = true;
    this.errorMsg = null;

    const { code, new_password } = this.form.value;

    this.authService.resetPassword(code!, new_password!).subscribe({
      next: () => {
        this.loading = false;
        this.exito = true;
      },error: (err) => {
        this.loading = false;
        this.errorMsg = 'El código es inválido o ya expiró. Solicita uno nuevo.';
      }
    });
  }

  irALogin(): void {
    this.router.navigate(['/login']);
  }
}
