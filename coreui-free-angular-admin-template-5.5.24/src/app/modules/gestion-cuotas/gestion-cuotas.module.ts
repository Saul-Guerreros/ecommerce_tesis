import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { GestionCuotasRoutingModule } from './gestion-cuotas-routing.module';
import { GestionCuotasComponent } from './gestion-cuotas.component';
import { ListProductoCuotaComponent } from './list-producto-cuota/list-producto-cuota.component';
import { FormCuotasComponent } from './form-cuotas/form-cuotas.component';
import { DeleteCuotaComponent } from './delete-cuota/delete-cuota.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    GestionCuotasRoutingModule,
    GestionCuotasComponent,
    ListProductoCuotaComponent,
    FormCuotasComponent,
    DeleteCuotaComponent,
  ],
})
export class GestionCuotasModule {}