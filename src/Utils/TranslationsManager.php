<?php

declare(strict_types=1);

namespace Bolt\Utils;

use Bolt\Entity\Field;
use Bolt\Entity\Field\CollectionField;
use Bolt\Entity\FieldParentInterface;
use Doctrine\Common\Collections\Collection;
use Knp\DoctrineBehaviors\Contract\Entity\TranslationInterface;

class TranslationsManager
{
    /** @var Collection */
    private $collections;

    /** @var array */
    private $translations = [];

    /** @var array */
    private $keys = [];

    public function __construct(Collection $collections, array $keys)
    {
        $this->collections = $collections;
        $this->translations = $this->populateTranslations();
        $this->keys = $keys;
    }

    public function applyTranslations(Field $field, string $collectionName, $orderId): void
    {
        if (! $field instanceof FieldParentInterface) {
            if ($this->hasTranslations($field, $collectionName, $orderId)) {
                $field->setTranslations($this->getTranslations($field, $collectionName, $orderId));
            }
        } else {
            /** @var Field $child */
            foreach ($field->getChildren() as $child) {
                $this->applyTranslations($child, $collectionName, $orderId);
            }
        }
    }

    private function getFieldChildrenTranslations(array &$translations, FieldParentInterface $field): void
    {
        /** @var Field $child */
        foreach ($field->getChildren() as $child) {
            if ($child instanceof FieldParentInterface && $child->hasChildren()) {
                $this->getFieldChildrenTranslations($translations, $child);
            }

            $translations[$child->getId()] = $child->getTranslations();
        }
    }

    private function populateTranslations(): array
    {
        $translations = [];

        /** @var CollectionField $collection */
        foreach ($this->collections as $collection) {
            $this->getFieldChildrenTranslations($translations, $collection);
        }

        return $translations;
    }

    private function getTranslations(Field $field, string $collectionName, $orderId): Collection
    {
        if (! $this->hasTranslations($field, $collectionName, $orderId)) {
            throw new \InvalidArgumentException(sprintf("'%s'does not have translations", $field->getName()));
        }

        if ($field->hasParent()) {
            $key = $this->keys[$collectionName][$field->getParent()->getName()][$orderId][$field->getName()];
        } else {
            $key = $this->keys[$collectionName][$field->getName()][$orderId];
        }

        $translations = $this->translations[$key];

        //do not return the translation for the current locale, so as to not override the newly submitted value
        return $translations->filter(function (TranslationInterface $translation) use ($field) {
            return $translation->getLocale() !== $field->getLocale();
        });
    }

    private function hasTranslations(Field $field, string $collectionName, $orderId): bool
    {
        if (empty($this->translations)) {
            //if there are no translations, we can return early.
            return false;
        }

        if ($field instanceof FieldParentInterface) {
            //assume FieldParentInterface always has translations (in order to process children)
            return true;
        }

        if ($field->hasParent()) {
            //find key for field with a parent
            if (! (array_key_exists($collectionName, $this->keys)
                && array_key_exists($field->getParent()->getName(), $this->keys[$collectionName])
                && array_key_exists($orderId, $this->keys[$collectionName][$field->getParent()->getName()])
                && array_key_exists($field->getName(), $this->keys[$collectionName][$field->getParent()->getName()][$orderId]))) {
                // if $this->keys[$collectionName][$parentName][$order][$name] does not exist, we can return.
                return false;
            }

            $key = $this->keys[$collectionName][$field->getParent()->getName()][$orderId][$field->getName()];
        } else {
            //find key for field without a parent
            if (! (array_key_exists($collectionName, $this->keys)
                && array_key_exists($field->getName(), $this->keys[$collectionName])
                && array_key_exists($orderId, $this->keys[$collectionName][$field->getName()])
                && array_key_exists('value', $this->keys[$collectionName][$field->getName()][$orderId]))) {
                // if $this->keys[$collectionName][$name][$order] does not exist, we can return.
                return false;
            }

            $key = $this->keys[$collectionName][$field->getName()][$orderId];
        }

        if (empty($key or ! is_numeric($key))) {
            // if key['value'] is empty or is not numeric (id), we can return.
            return false;
        }

        $value = (int) $key;

        return array_key_exists($value, $this->translations);
    }
}
