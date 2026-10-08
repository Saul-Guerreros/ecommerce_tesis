import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { PlantillasFichaRoutingModule } from './plantillas-ficha-routing.module';
import { PlantillasFichaComponent } from './plantillas-ficha.component';
import { ListCategoriaAtributoComponent } from './list-categoria-atributo/list-categoria-atributo.component';
import { FormCategoriaAtributoComponent } from './form-categoria-atributo/form-categoria-atributo.component';
import { DeleteCategoriaAtributoComponent } from './delete-categoria-atributo/delete-categoria-atributo.component';

// El NgModule solo actúa como contenedor de rutas para el lazy loading:
// todos los componentes son standalone.
@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    PlantillasFichaRoutingModule,
    PlantillasFichaComponent,
    ListCategoriaAtributoComponent,
    FormCategoriaAtributoComponent,
    DeleteCategoriaAtributoComponent,
  ],
})
export class PlantillasFichaModule {}
