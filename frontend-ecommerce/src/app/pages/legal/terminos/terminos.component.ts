import { Component } from '@angular/core';
import { RouterModule } from '@angular/router';
import { DATOS_TIENDA } from '../../../config/datos-tienda';

@Component({
  selector: 'app-terminos',
  standalone: true,
  imports: [RouterModule],
  templateUrl: './terminos.component.html',
})
export class TerminosComponent {
  readonly tienda = DATOS_TIENDA;
}