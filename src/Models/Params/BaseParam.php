<?php

namespace Crm\SegmentModule\Params;

use Crm\ApplicationModule\Criteria\CriteriaParam;
use Crm\SegmentModule\Criteria\InvalidCriteriaException;
use Nette\Utils\DateTime;

abstract class BaseParam implements CriteriaParam
{
    protected $type;

    private $key;

    private $required;

    private $default;

    private $group;

    private $help;

    private $label;

    protected $data = null;

    public function __construct(string $key, string $label, string $help, bool $required = false, $default = null, string $group = null)
    {
        $this->key = $key;
        $this->required = $required;
        $this->default = $default;
        $this->group = $group;
        $this->help = $help;
        $this->label = $label;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function default()
    {
        return $this->default;
    }

    public function help()
    {
        return $this->help;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function group(): string
    {
        return $this->group == null ? 'General' : $this->group;
    }

    public function blueprint(): array
    {
        $result = [
            'type' => $this->type(),
            'required' => $this->required(),
            'default' => $this->default(),
            'help' => $this->help(),
            'label' => $this->label(),
        ];
        if ($this->group()) {
            $result['group'] = $this->group;
        }
        return $result;
    }

    abstract public function isValid($data): Validation;

    abstract public function equals(BaseParam $param): bool;

    public function setData($data): self
    {
        if (!$this->isValid($data)) {
            throw new InvalidCriteriaException("Trying to set invalid data");
        }
        $this->data = $data;
        return $this;
    }

    public function hasData(): bool
    {
        return $this->data !== null;
    }

    protected function validDateFormat($date): bool
    {
        return DateTime::createFromFormat("Y-m-d\TH:i:s.uP", $date) !== false
            || DateTime::createFromFormat(DateTime::RFC3339, $date) !== false;
    }

    protected function escapeKey($key)
    {
        $key = str_replace('.', '`.`', $key);
        return "`{$key}`";
    }
}
