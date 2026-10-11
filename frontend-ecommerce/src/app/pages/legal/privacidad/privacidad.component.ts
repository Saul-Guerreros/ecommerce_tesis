import { Component } from '@angular/core';
import { RouterModule } from '@angular/router';
import { DATOS_TIENDA } from '../../../config/datos-tienda';

@Component({
  selector: 'app-privacidad',
  standalone: true,
  imports: [RouterModule],
  templateUrl: './privacidad.component.html',
})
export class PrivacidadComponent {
  readonly tienda = DATOS_TIENDA;
}