import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CuotaOpcion } from '../../../core/models/vitrina.model';

@Component({
  selector: 'app-cuotero',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './cuotero.component.html',
})
export class CuoteroComponent {
  @Input({ required: true }) cuotas: CuotaOpcion[] = [];
}