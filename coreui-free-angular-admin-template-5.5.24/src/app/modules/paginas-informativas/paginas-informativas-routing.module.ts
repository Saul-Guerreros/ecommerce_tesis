import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PaginasInformativasComponent } from './paginas-informativas.component';

const routes: Routes = [
  { path: '', component: PaginasInformativasComponent },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class PaginasInformativasRoutingModule {}