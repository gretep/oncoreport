import { injectable } from 'tsyringe';
import Entity, { field } from '../../apiConnector/entity/entity';
import { SuspensionReasonAdapter } from '../adapters';

@injectable()
export default class SuspensionReason extends Entity {
  @field({
    readonly: true,
  })
  public name = '';

  public constructor(adapter: SuspensionReasonAdapter) {
    super(adapter);
  }
}