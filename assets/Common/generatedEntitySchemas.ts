import media from '../data/entity/media.json';

type EntitySchema = { name: string };

export default function getGeneratedEntitySchemas(): Record<string, EntitySchema> {
  return {
    [media.name]: media,
  };
}
