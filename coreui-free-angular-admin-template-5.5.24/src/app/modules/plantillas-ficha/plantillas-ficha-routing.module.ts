import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PlantillasFichaComponent } from './plantillas-ficha.component';
import { ListCategoriaAtributoComponent } from './list-categoria-atributo/list-categoria-atributo.component';

const routes: Routes = [
  {
    path: '',
    component: PlantillasFichaComponent,
    children: [{ path: '', component: ListCategoriaAtributoComponent }],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class PlantillasFichaRoutingModule {}
