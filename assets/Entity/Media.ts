import AbstractApiEntity from '@wexample/js-api-entity/Common/AbstractApiEntity';
import schema from '../data/entity/media.json';

export default class Media extends AbstractApiEntity {
  static readonly entityName = 'media';

  static retrieveEntitySchema() {
    return schema;
  }
}
