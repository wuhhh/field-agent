<?php

namespace craftcms\fieldagent\models;

use craft\base\Model;

/**
 * Operation model for tracking field generation operations
 */
class Operation extends Model
{
    public string $id;
    public string $type; // 'generate', 'basic-fields', 'prompt'
    public string $source; // config filename, prompt text, etc.
    public int $timestamp;
    public array $createdFields = [];
    public array $failedFields = [];
    public array $createdEntryTypes = [];
    public array $failedEntryTypes = [];
    public array $createdSections = [];
    public array $failedSections = [];
    public array $createdCategoryGroups = [];
    public array $failedCategoryGroups = [];
    public array $createdTagGroups = [];
    public array $failedTagGroups = [];
    public ?string $description = null;

    public function rules(): array
    {
        return [
            [['id', 'type', 'source'], 'required'],
            [['id', 'type', 'source', 'description'], 'string'],
            [['timestamp'], 'integer'],
            [['createdFields', 'failedFields', 'createdEntryTypes', 'failedEntryTypes', 'createdSections', 'failedSections', 'createdCategoryGroups', 'failedCategoryGroups', 'createdTagGroups', 'failedTagGroups'], 'safe'],
        ];
    }

    public function getFormattedTimestamp(): string
    {
        return date('Y-m-d H:i:s', $this->timestamp);
    }

    public function getFieldCount(): int
    {
        return count($this->createdFields);
    }

    public function getEntryTypeCount(): int
    {
        return count($this->createdEntryTypes);
    }

    public function getSectionCount(): int
    {
        return count($this->createdSections);
    }

    public function getCategoryGroupCount(): int
    {
        return count($this->createdCategoryGroups);
    }

    public function getTagGroupCount(): int
    {
        return count($this->createdTagGroups);
    }

    public function getTotalCreatedCount(): int
    {
        return count($this->createdFields) + count($this->createdEntryTypes) + count($this->createdSections) + count($this->createdCategoryGroups) + count($this->createdTagGroups);
    }

    public function toArray($fields = [], $expand = [], $recursive = true)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'source' => $this->source,
            'timestamp' => $this->timestamp,
            'createdFields' => $this->createdFields,
            'failedFields' => $this->failedFields,
            'createdEntryTypes' => $this->createdEntryTypes,
            'failedEntryTypes' => $this->failedEntryTypes,
            'createdSections' => $this->createdSections,
            'failedSections' => $this->failedSections,
            'createdCategoryGroups' => $this->createdCategoryGroups,
            'failedCategoryGroups' => $this->failedCategoryGroups,
            'createdTagGroups' => $this->createdTagGroups,
            'failedTagGroups' => $this->failedTagGroups,
            'description' => $this->description,
        ];
    }

    public static function fromArray(array $data): self
    {
        $operation = new self();
        $operation->id = $data['id'] ?? '';
        $operation->type = $data['type'] ?? '';
        $operation->source = $data['source'] ?? '';
        $operation->timestamp = $data['timestamp'] ?? time();
        $operation->createdFields = $data['createdFields'] ?? [];
        $operation->failedFields = $data['failedFields'] ?? [];
        $operation->createdEntryTypes = $data['createdEntryTypes'] ?? [];
        $operation->failedEntryTypes = $data['failedEntryTypes'] ?? [];
        $operation->createdSections = $data['createdSections'] ?? [];
        $operation->failedSections = $data['failedSections'] ?? [];
        $operation->createdCategoryGroups = $data['createdCategoryGroups'] ?? [];
        $operation->failedCategoryGroups = $data['failedCategoryGroups'] ?? [];
        $operation->createdTagGroups = $data['createdTagGroups'] ?? [];
        $operation->failedTagGroups = $data['failedTagGroups'] ?? [];
        $operation->description = $data['description'] ?? null;
        return $operation;
    }
}