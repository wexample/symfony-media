<?php

namespace Wexample\SymfonyMedia\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Wexample\SymfonyMedia\Entity\Media;

/**
 * Who may browse the library and read its files (`MEDIA_VIEW`), and who may
 * arrange it (`MEDIA_MANAGE`): the roles the application configured. The
 * subject, a media or a folder, is passed along so that an application can
 * add its own voter, finer than a role.
 */
final class MediaVoter extends Voter
{
    public const string VIEW = 'MEDIA_VIEW';

    public const string MANAGE = 'MEDIA_MANAGE';

    public function __construct(
        private readonly Security $security,
        #[Autowire(param: 'wexample_symfony_media.view_role')]
        private readonly string $viewRole,
        #[Autowire(param: 'wexample_symfony_media.manage_role')]
        private readonly string $manageRole,
    ) {
    }

    protected function supports(
        string $attribute,
        mixed $subject
    ): bool {
        return in_array($attribute, [self::VIEW, self::MANAGE], true);
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null
    ): bool {
        $restricted = $subject instanceof Media && $subject->isRestricted();

        return $this->security->isGranted(self::VIEW === $attribute && ! $restricted ? $this->viewRole : $this->manageRole);
    }
}
