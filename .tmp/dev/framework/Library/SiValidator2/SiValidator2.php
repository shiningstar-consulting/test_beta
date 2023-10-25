<?php

namespace SiValidator2;

use SiValidator2\Rules\AcceptedIfRule;
use SiValidator2\Rules\AcceptedRule;
use SiValidator2\Rules\ActiveUrlRule;
use SiValidator2\Rules\AfterDateRule;
use SiValidator2\Rules\AfterOrEqualDateRule;
use SiValidator2\Rules\AlphaDashRule;
use SiValidator2\Rules\AlphaNumRule;
use SiValidator2\Rules\AlphaRule;
use SiValidator2\Rules\BeforeDateRule;
use SiValidator2\Rules\BeforeOrEqualDateRule;
use SiValidator2\Rules\BetweenRule;
use SiValidator2\Rules\BooleanRule;
use SiValidator2\Rules\ConfirmedRule;
use SiValidator2\Rules\DateEqualsRule;
use SiValidator2\Rules\DateFormatRule;
use SiValidator2\Rules\DateRule;
use SiValidator2\Rules\DeclinedIfRule;
use SiValidator2\Rules\DeclinedRule;
use SiValidator2\Rules\DifferentRule;
use SiValidator2\Rules\DigitsBetweenRule;
use SiValidator2\Rules\DigitsRule;
use SiValidator2\Rules\EmailRule;
use SiValidator2\Rules\ExcludeIfRule;
use SiValidator2\Rules\ExcludeUnlessRule;
use SiValidator2\Rules\ExcludeWithoutRule;
use SiValidator2\Rules\ExistsRule;
use SiValidator2\Rules\IntegerRule;
use SiValidator2\Rules\JsonRule;
use SiValidator2\Rules\MaxBytesRule;
use SiValidator2\Rules\MaxRule;
use SiValidator2\Rules\MinRule;
use SiValidator2\Rules\NotRegexRule;
use SiValidator2\Rules\NullableRule;
use SiValidator2\Rules\NumericRule;
use SiValidator2\Rules\RegexRule;
use SiValidator2\Rules\RequiredRule;
use SiValidator2\Rules\RuleInterface;
use SiValidator2\Rules\StringRule;
use SiValidator2\Rules\TimezoneRule;
use SiValidator2\Rules\UniqueRule;

class SiValidator2
{
    private $results = [];
    private static $currentLang = 'en';  // default language
    private static $ruleMappings = [
        'required' => [RequiredRule::class, []],
        'accepted' => [AcceptedRule::class, []],
        'accepted_if' => [AcceptedIfRule::class, ['other', 'value']],
        'active_url' => [ActiveUrlRule::class, []],
        'after' => [AfterDateRule::class, ['date']],
        'after_or_equal' => [AfterOrEqualDateRule::class, ['date']],
        'alpha' => [AlphaRule::class, []],
        'alpha_dash' => [AlphaDashRule::class, []],
        'alpha_num' => [AlphaNumRule::class, []],
        'before' => [BeforeDateRule::class, []],
        'before_or_equal' => [BeforeOrEqualDateRule::class, []],
        'date_equals' => [DateEqualsRule::class, ['date']],
        'date_format' => [DateFormatRule::class, ['format']],
        'date' => [DateRule::class, []],
        'between' => [BetweenRule::class, []],
        'boolean' => [BooleanRule::class, ['min','max']],
        'confirmed' => [ConfirmedRule::class, ['field']],
        'declined' => [DeclinedRule::class, []],
        'declined_if' => [DeclinedIfRule::class, ['other','value']],
        'different' => [DifferentRule::class, ['field']],
        'digits' => [DigitsRule::class, ['value']],
        'digits_between' => [DigitsBetweenRule::class, ['min','max']],
        'email' => [EmailRule::class, []],
        'exclude_if' => [ExcludeIfRule::class, ['other', 'value']],
        'exclude_unless' => [ExcludeUnlessRule::class, ['other', 'value']],
        'exclude_without' => [ExcludeWithoutRule::class, ['other']],
        'exists' => [ExistsRule::class, ['table', 'column']],
        'unique' => [UniqueRule::class, ['table', 'column']],
        'timezone' => [TimezoneRule::class, []],
        'string' => [StringRule::class, []],
        'regex' => [RegexRule::class, ['pattern']],
        'not_regex' => [NotRegexRule::class, ['pattern']],
        'numeric' => [NumericRule::class, []],
        'min' => [MinRule::class, ['value']],
        'max' => [MaxRule::class, ['value']],
        'max_bytes' => [MaxBytesRule::class,['value']],
        'json' => [JsonRule::class, []],
        'integer' => [IntegerRule::class, []],
        //'distinct' => [DistinctRule::class,['mode'], 'apply_to_array' => true],
    ];

    private function __construct(array $results)
    {
        $this->results = $results;
    }
    public static function make(array $values, array $rules, array $labels = [], array $messages = []): SiValidator2
    {
        $results = [];
        $translations = SIVALIDATELANG;

        foreach ($rules as $field => $ruleList) {
            $label = $labels[$field] ?? $field;
            $value = self::getValueByPath($values, $field);

            // If wildcard is found in the field name
            if (strpos($field, '.*') !== false) {
                $baseField = preg_replace('/\.\*$/', '', $field);
                $arrayValues = self::getValueByPath($values, $baseField);

                if (is_array($arrayValues)) {
                    foreach ($arrayValues as $index => $subValue) {
                        $subLabels = [];
                        foreach($labels as $labelKey => $labelValue) {
                            $subLabelKey = str_replace('.*', ".{$index}", $labelKey);
                            $subLabels[$subLabelKey] =  $labelValue;
                        }
                        $subField = str_replace('.*', ".{$index}", $field);
                        $subRules = [$subField => $ruleList];
                        $subValidator = self::make($values, $subRules, $subLabels, $messages);
                        $results = array_merge($results, $subValidator->getResults());
                    }
                }
            } else {
                $results = array_merge($results, self::applyRules($field, $value, $ruleList, $values, $label, $translations, $messages));
            }
        }

        return new SiValidator2($results);
    }

    private static function getValueByPath($array, $path)
    {
        $segments = explode('.', $path);
        foreach ($segments as $segment) {
            if ($segment === '*') {
                return $array;
            }
            if (!isset($array[$segment])) {
                return null;
            }
            $array = $array[$segment];
        }
        return $array;
    }

    private static function applyRules($field, $value, $ruleList, $allValues, $label, $translations, $messages)
    {
        $results = [];

        if (in_array('nullable', $ruleList) && ($value === null || $value === '')) {
            return $results;  // Return empty results, as other validations should be skipped
        }

        foreach ($ruleList as $rule) {
            $parameters = [];
            $placeholders = [];
            if ($rule instanceof RuleInterface) {
                // 何もしない（既にRuleインスタンスなのでそのまま利用する）
            } elseif (is_string($rule) && strpos($rule, ':') !== false) {
                list($ruleName, $params) = explode(':', $rule, 2);
                $parameters = explode(',', $params);
                if ($ruleName === 'unique') {
                    $table = $parameters[0];
                    $column = $parameters[1] ?? $field;
                    $rule = new UniqueRule($table, $column);
                } elseif (isset(self::$ruleMappings[$ruleName])) {
                    list($ruleClass, $placeholders) = self::$ruleMappings[$ruleName];
                    $rule = new $ruleClass(...$parameters);
                }
            } elseif (isset(self::$ruleMappings[$rule])) {
                list($ruleClass, $placeholders) = self::$ruleMappings[$rule];
                if (in_array('field', $placeholders)) {
                    $rule = new $ruleClass($field);
                } else {
                    $rule = new $ruleClass();
                }
            }

            foreach($parameters as $k => $parameter) {
                if(array_key_exists($parameter, $allValues)) {
                    $parameters[$k] = $allValues[$parameter];
                }
            }

            if ($rule instanceof RuleInterface && $rule::processable($value)) {
                $isValid = $rule->validate($value, $allValues);  // $values を $allValues に変更
                if (!$isValid && in_array($rule->name(), ['exclude_if', 'exclude_unless', 'exclude_without'])) {
                    continue;
                }
                $messageTemplate = $messages[$field][$rule->name()] ??
                    $translations[self::$currentLang][$rule->name()] ??
                    $rule->message();

                // Replace placeholders in the message
                foreach ($placeholders as $index => $placeholder) {
                    $messageTemplate = str_replace(":$placeholder", $parameters[$index] ?? '', $messageTemplate);
                }
                $messageTemplate = str_replace(':attribute', $label, $messageTemplate);
                $results[] = new SiValidateResult($field, $value, $isValid, $isValid ? null : $messageTemplate);
                if (!$isValid) {
                    break;
                }
            }
        }
        return $results;
    }
    public static function setLanguage(string $lang): void
    {
        self::$currentLang = $lang;
    }

    public function isError(): bool
    {
        foreach ($this->results as $result) {
            if (!$result->isValid()) {
                return true;
            }
        }
        return false;
    }

    public function getResults(): array
    {
        $formattedResults = [];

        foreach ($this->results as $key => $result) {
            if (is_array($result)) {
                foreach ($result as $res) {
                    $field = $res->getField();
                    $formattedResults[$field] = $res;
                }
            } else {
                $formattedResults[$result->getField()] = $result;
            }
        }

        return $formattedResults;
    }

    public function toArray(): array
    {
        $results = $this->getResults();

        // Convert each result into an associative array
        $formatted = [];
        foreach ($results as $key => $result) {
            $formatted[$key] = $result->toArray();
        }

        return $formatted;
    }
}
