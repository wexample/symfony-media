import AbstractApiRepository from '@wexample/js-api-entity/Common/AbstractApiRepository';
import Media from '../Entity/Media.js';

export default class MediaRepository extends AbstractApiRepository<Media> {
  static getEntityType() {
    return Media;
  }
}
