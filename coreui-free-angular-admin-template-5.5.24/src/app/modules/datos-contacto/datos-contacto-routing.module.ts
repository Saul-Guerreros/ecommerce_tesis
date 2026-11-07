import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { DatosContactoComponent } from './datos-contacto.component';

const routes: Routes = [{ path: '', component: DatosContactoComponent }];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class DatosContactoRoutingModule {}