import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { TiposDocumentoComponent } from './tipos-documento.component';
import { ListTipoDocumentoComponent } from './list-tipo-documento/list-tipo-documento.component';

const routes: Routes = [
  { path: '', component: TiposDocumentoComponent, children: [{ path: '', component: ListTipoDocumentoComponent }] },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class TiposDocumentoRoutingModule {}
