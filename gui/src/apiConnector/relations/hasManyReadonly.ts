import { InjectionToken, container } from 'tsyringe';
import { Arrayable, MapValueType, PartialObject } from '../interfaces/common';
import { EntityObject, RepositoryObject } from '../interfaces/entity';

type Fillable<R> = number | R | PartialObject<R>;

export default class HasManyReadonly<R extends EntityObject> extends Array<R> {
  /**
   * A repository object used for caching and entity building
   * @protected
   */
  protected repository!: RepositoryObject<R>;

  constructor(
    relatedRepositoryToken: InjectionToken<RepositoryObject<R>> | number,
    data: Fillable<R>[] = []
  ) {
    super();
    if (typeof relatedRepositoryToken !== 'number') {
      this.repository = container.resolve(relatedRepositoryToken);
      this.syncFill(data);
    }
  }

  /**
   * Undocumented - Do not use
   */
  public syncFill(data: Fillable<R>[]): this {
    if (this.length > 0) {
      this.slice(0, this.length);
    }
    this.push(
      ...data.map((o) => {
        if (typeof o === 'number') {
          return this.repository.createStubEntity(o);
        }
        if (typeof o === 'object' && o.isEntity && o.isEntity()) {
          return o as R;
        }
        return this.repository.createEntitySync(o);
      })
    );
    return this;
  }

  /**
   * Attach an entity to this relationship
   * @param entities
   */
  public attach(entities: Arrayable<R>): void {
    super.push(...(Array.isArray(entities) ? entities : [entities]));
  }

  /**
   * Convert this object to a list of identifiers
   */
  public toDataObject(): number[] {
    return this.map((o) => o.id);
  }

  /**
   * Serializes this object.
   */
  public serialize(): MapValueType {
    return this.map((o) => o.id);
  }
}
