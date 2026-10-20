# symfony-media

Version: 4.0.0

## What it keeps

A media library. A `Entity\Media` is a file kept for itself, in a `Entity\MediaFolder` or at the root; folders nest. An entity of the application uses a media through a plain relation, and the same media may serve in several places:

```php
#[ORM\ManyToOne(targetEntity: Media::class)]
private ?Media $cover = null;

#[ORM\ManyToMany(targetEntity: Media::class)]
private Collection $attachments;
```

Deleting a media still used: a ManyToMany link goes with it (Doctrine cascades its join table); a ManyToOne fails on the foreign key unless it says `onDelete: 'SET NULL'` — whether a cover may vanish is the application's call.

## Storing

```php
$media = $storage->import($uploadedPath, 'Cover photo.png', $folder);
```

`Service\MediaStorage::import()` moves the file into the library, reads its type, its size and, for an image, its dimensions, and flushes the media. The file lies at `<storage_dir>/<two first characters of the id>/<id>.<extension>`: named after the id, so that two media never collide and nothing a person typed reaches the disk. What it was called is `getOriginalName()`; `getName()` is free to change.

```yaml
# config/packages/wexample_symfony_media.yaml
wexample_symfony_media:
    storage_dir: '%kernel.project_dir%/var/media'   # out of public/: every file goes through the access check
    thumbnail_sizes: [160, 480]                       # the only widths drawn
    view_role: ROLE_USER                              # browse and read
    manage_role: ROLE_ADMIN                           # add, rename, move, delete
```

## Reading

`GET /_media/{id}` sends the file, `GET /_media/{id}/thumbnail/{width}` a webp copy drawn the first time it is asked for, at one of the configured widths, never larger than the original (GD: jpeg, png, gif, webp). Both need `MEDIA_VIEW`. A file the browser shows safely — a raster image, a PDF, a sound, a video, plain text — is sent inline; anything else, an SVG or an HTML file among them, is downloaded, and every answer is sandboxed (`Content-Security-Policy: sandbox`). `?download=1` downloads whatever it is.

`Security\MediaVoter` grants `MEDIA_VIEW` and `MEDIA_MANAGE` from the configured roles, with the media or folder as subject, so that an application can add a finer voter.

## Restricted media and access grants

A media marked `restricted` (`setRestricted(true)`) is left out of the library's lists and refused to its readers: only the managers open it. Anyone else downloads it through a grant — a file sold, a private document sent to someone without an account:

```php
$grant = $mediaAccessService->grant($media, new DateTimeImmutable('+30 days'), maxUses: 5, origin: 'shop_cart_item:'.$item->getId());
$entityManager->flush();

$url = $mediaAccessService->getUrl($grant);   // absolute, signed, refused once the grant expires
```

The link (`/_media/grant/{id}`, route `media_grant`) is signed by Symfony's `UriSigner`: tampered with or past its date, it leads nowhere (404). Each download is counted on the grant, its row locked so that two downloads at once cannot both take the last one; a grant used up answers 410. `origin` tells who gave it, in the giver's words; `MediaAccessGrantRepository::findByOrigin()` finds them again.

## Browsing and arranging

`api/media/list?folder=<id>&page=&length=` lists a level of the library — its folders, then its media, by name — in the shape symfony-design-system's file explorer reads: `{ id, path, name, type: directory|file, hasChildren, size, modifiedAt }`, plus `mimeType`, `width`, `height`, `url` and `thumbnailUrl` for a media. `api/media/folder`, `rename`, `move` and `delete` arrange it, JSON only, an item named `{ type: media|folder, id }`. A folder is never moved inside itself, nor deleted while it holds anything.

symfony-media-ds draws the library and the field picking media in a form; symfony-media-demo shows both.

## Table of Contents

- [What it keeps](#what-it-keeps)
- [Storing](#storing)
- [Reading](#reading)
- [Restricted media and access grants](#restricted-media-and-access-grants)
- [Browsing and arranging](#browsing-and-arranging)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

## Dependencies

- php: >=8.5
- ext-gd: *
- wexample/symfony-api: >=14.0.0
- wexample/symfony-helpers: >=17.0.0
- wexample/php-pseudocode: >=2.2.0
- wexample/symfony-pseudocode: >=4.0.0
- symfony/mime: ^7.4 || ^8.0
- symfony/security-bundle: ^7.4 || ^8.0

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

Built and maintained by [Romain Weeger](https://weeger.io).

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
