<?php

namespace Zakirkun\Jett\Validation;

use Zakirkun\Jett\Database\Connection;

class Validator
{
    protected array $data;
    protected array $rules;
    protected array $errors = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public function validate(): bool
    {
        foreach ($this->rules as $field => $rules) {
            $rules = explode('|', $rules);
            
            foreach ($rules as $rule) {
                $parameters = [];
                
                if (str_contains($rule, ':')) {
                    [$rule, $parameter] = explode(':', $rule);
                    $parameters = explode(',', $parameter);
                }
                
                $method = 'validate' . ucfirst($rule);
                
                if (method_exists($this, $method)) {
                    if (!$this->$method($field, $parameters)) {
                        $this->addError($field, $rule);
                    }
                }
            }
        }
        
        return empty($this->errors);
    }

    protected function validateRequired(string $field): bool
    {
        return isset($this->data[$field]) && !empty($this->data[$field]);
    }

    protected function validateEmail(string $field): bool
    {
        return filter_var($this->data[$field] ?? '', FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateMin(string $field, array $parameters): bool
    {
        $value = $this->data[$field] ?? '';
        $min = (int) ($parameters[0] ?? 0);
        
        if (is_numeric($value)) {
            return $value >= $min;
        }
        
        return mb_strlen($value) >= $min;
    }

    protected function validateMax(string $field, array $parameters): bool
    {
        $value = $this->data[$field] ?? '';
        $max = (int) ($parameters[0] ?? 0);
        
        if (is_numeric($value)) {
            return $value <= $max;
        }
        
        return mb_strlen($value) <= $max;
    }

    protected function validateUnique(string $field, array $parameters): bool
    {
        $table = $parameters[0] ?? null;
        if (!$table) return false;

        $value = $this->data[$field] ?? null;
        if ($value === null) return true;

        $query = "SELECT COUNT(*) as count FROM {$table} WHERE {$field} = ?";
        $stmt = Connection::getInstance()->prepare($query);
        $stmt->execute([$value]);
        $result = $stmt->fetch();

        return ($result['count'] ?? 1) === 0;
    }

    protected function validateNumeric(string $field): bool
    {
        return is_numeric($this->data[$field] ?? '');
    }

    protected function validateInteger(string $field): bool
    {
        return filter_var($this->data[$field] ?? '', FILTER_VALIDATE_INT) !== false;
    }

    protected function validateBoolean(string $field): bool
    {
        $value = $this->data[$field] ?? null;
        return in_array($value, [true, false, 0, 1, '0', '1'], true);
    }

    protected function validateDate(string $field): bool
    {
        $value = $this->data[$field] ?? '';
        return strtotime($value) !== false;
    }

    protected function validateIn(string $field, array $parameters): bool
    {
        return in_array($this->data[$field] ?? '', $parameters);
    }

    protected function addError(string $field, string $rule): void
    {
        $this->errors[$field][] = $this->getErrorMessage($field, $rule);
    }

    protected function getErrorMessage(string $field, string $rule): string
    {
        $messages = [
            'required' => 'The :field field is required.',
            'email' => 'The :field must be a valid email address.',
            'min' => 'The :field must be at least :min.',
            'max' => 'The :field may not be greater than :max.',
            'unique' => 'The :field has already been taken.',
            'numeric' => 'The :field must be a number.',
            'integer' => 'The :field must be an integer.',
            'boolean' => 'The :field must be true or false.',
            'date' => 'The :field must be a valid date.',
            'in' => 'The selected :field is invalid.'
        ];

        return str_replace(':field', $field, $messages[$rule] ?? 'The :field is invalid.');
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
