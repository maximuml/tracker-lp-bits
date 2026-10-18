<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Livewire\Attributes\Locked;
use Livewire\Component;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Tests\Attributes\TestCategory;

/**
 * Ratchet: public properties on Livewire components are client-writable
 * unless marked #[Locked]. Every public property declared on a component
 * in app/Livewire must either be locked or appear in CLIENT_WRITABLE —
 * the explicit list of props the browser is allowed to set (wire:model
 * inputs and method-toggled UI state). This keeps mount-time resource
 * identifiers and permission flags server-controlled.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class LivewireLockedPropertiesTest extends TestCase
{
    /**
     * Props intentionally writable from the client, keyed Component::prop.
     * Adding an entry requires justification: anything feeding a query,
     * an authorization decision, or a resource id must be locked instead.
     */
    private const CLIENT_WRITABLE = [
        'App\\Livewire\\BbcodeEditor::body' => true,
        'App\\Livewire\\BbcodeEditor::invalid' => true,
        'App\\Livewire\\BbcodeEditor::previewMode' => true,
        'App\\Livewire\\BookmarkIcon::bookmarked' => true,
        'App\\Livewire\\CommentSection::page' => true,
        'App\\Livewire\\CommentSection::status' => true,
        'App\\Livewire\\CommentSection::text' => true,
        'App\\Livewire\\DescrRow::open' => true,
        'App\\Livewire\\IndexPoll::choice' => true,
        'App\\Livewire\\MagicSection::showAll' => true,
        'App\\Livewire\\MagicSection::status' => true,
        'App\\Livewire\\NewsItem::open' => true,
        'App\\Livewire\\PeerList::open' => true,
        'App\\Livewire\\SetlistLookup::invalid' => true,
        'App\\Livewire\\SetlistLookup::name' => true,
        'App\\Livewire\\Shoutbox::open' => true,
        'App\\Livewire\\Shoutbox::status' => true,
        'App\\Livewire\\Shoutbox::text' => true,
        'App\\Livewire\\ThanksSection::status' => true,
        'App\\Livewire\\TorrentFileList::open' => true,
        'App\\Livewire\\UserTorrentList::open' => true,
    ];

    public function test_livewire_public_properties_are_locked_or_explicitly_writable(): void
    {
        $violations = [];

        foreach (glob(dirname(__DIR__, 2).'/app/Livewire/*.php') ?: [] as $file) {
            $class = 'App\\Livewire\\'.basename($file, '.php');
            if (! class_exists($class) || ! is_subclass_of($class, Component::class)) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic() || $property->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                $key = $class.'::'.$property->getName();
                if (isset(self::CLIENT_WRITABLE[$key]) || $property->getAttributes(Locked::class) !== []) {
                    continue;
                }

                $violations[] = $key;
            }
        }

        sort($violations);
        $this->assertSame(
            [],
            $violations,
            "Public Livewire properties missing #[Locked] (or a CLIENT_WRITABLE entry):\n".implode("\n", $violations),
        );
    }
}
