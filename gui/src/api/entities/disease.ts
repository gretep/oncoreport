import { injectable } from 'tsyringe';
import { Entity, field } from '../../apiConnector';
import { DiseaseAdapter } from '../adapters';

@injectable()
export default class Disease extends Entity {
  @field({
    readonly: true,
  })
  public icd_code = '';

  @field({
    readonly: true,
  })
  public name = '';

  @field({
    readonly: true,
  })
  public tumor = false;

  public constructor(adapter: DiseaseAdapter) {
    super(adapter);
  }
}
