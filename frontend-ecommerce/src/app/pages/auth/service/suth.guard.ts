import { CanActivateFn } from '@angular/router';

export const suthGuard: CanActivateFn = (route, state) => {
  return true;
};
