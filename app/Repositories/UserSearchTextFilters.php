<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Validators;
use Illuminate\Contracts\Database\Query\Builder;
use InvalidArgumentException;

/**
 * Text/wildcard where-clauses for the administrative user search
 * (username, email, modcomment).
 */
final class UserSearchTextFilters
{
    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyName(Builder $userQuery, array $params): array
    {
        $n = trim((string) ($params['n'] ?? ''));
        if ($n === '') {
            return [];
        }
        $names = explode(' ', $n);
        $names_inc = [];
        $names_exc = [];
        foreach ($names as $name) {
            if (substr($name, 0, 1) == '~') {
                if ($name == '~') {
                    continue;
                }
                $names_exc[] = substr($name, 1);
            } else {
                $names_inc[] = $name;
            }
        }
        if (! empty($names_inc)) {
            $userQuery->where(function ($query) use ($names_inc) {
                $first = true;
                foreach ($names_inc as $name) {
                    if (! $this->hasWildcard($name)) {
                        $method = $first ? 'where' : 'orWhere';
                        $query->$method('u.username', $name);
                    } else {
                        $name = str_replace(['?', '*'], ['_', '%'], $name);
                        $method = $first ? 'where' : 'orWhere';
                        $query->$method('u.username', 'like', $name);
                    }
                    $first = false;
                }
            });
        }
        if (! empty($names_exc)) {
            $userQuery->where(function ($query) use ($names_exc) {
                foreach ($names_exc as $name) {
                    if (! $this->hasWildcard($name)) {
                        $query->where('u.username', '!=', $name);
                    } else {
                        $name = str_replace(['?', '*'], ['_', '%'], $name);
                        $query->where('u.username', 'not like', $name);
                    }
                }
            });
        }

        return ['n='.rawurlencode($n)];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyEmail(Builder $userQuery, array $params): array
    {
        $em = trim((string) ($params['em'] ?? ''));
        if ($em === '') {
            return [];
        }
        $emaila = explode(' ', $em);
        $userQuery->where(function ($query) use ($emaila) {
            $first = true;
            foreach ($emaila as $email) {
                if (strpos($email, '*') === false && strpos($email, '?') === false && strpos($email, '%') === false) {
                    if (! Validators::isEmail($email)) {
                        throw new InvalidArgumentException('Bad email.');
                    }
                    $method = $first ? 'where' : 'orWhere';
                    $query->$method('u.email', $email);
                } else {
                    $sql_email = str_replace(['?', '*'], ['_', '%'], $email);
                    $method = $first ? 'where' : 'orWhere';
                    $query->$method('u.email', 'like', $sql_email);
                }
                $first = false;
            }
        });

        return ['em='.rawurlencode($em)];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyComment(Builder $userQuery, array $params, bool $hasModcomment): array
    {
        $co = trim((string) ($params['co'] ?? ''));
        if ($co === '' || ! $hasModcomment) {
            return [];
        }
        $comments = explode(' ', $co);
        $comments_inc = [];
        $comments_exc = [];
        foreach ($comments as $comment) {
            if (substr($comment, 0, 1) == '~') {
                if ($comment == '~') {
                    continue;
                }
                $comments_exc[] = substr($comment, 1);
            } else {
                $comments_inc[] = $comment;
            }
        }
        if (! empty($comments_inc)) {
            $userQuery->where(function ($query) use ($comments_inc) {
                $first = true;
                foreach ($comments_inc as $comment) {
                    if (! $this->hasWildcard($comment)) {
                        $method = $first ? 'where' : 'orWhere';
                        $query->$method('u.modcomment', 'like', '%'.$comment.'%');
                    } else {
                        $comment = str_replace(['?', '*'], ['_', '%'], $comment);
                        $method = $first ? 'where' : 'orWhere';
                        $query->$method('u.modcomment', 'like', $comment);
                    }
                    $first = false;
                }
            });
        }
        if (! empty($comments_exc)) {
            $userQuery->where(function ($query) use ($comments_exc) {
                foreach ($comments_exc as $comment) {
                    if (! $this->hasWildcard($comment)) {
                        $query->where('u.modcomment', 'not like', '%'.$comment.'%');
                    } else {
                        $comment = str_replace(['?', '*'], ['_', '%'], $comment);
                        $query->where('u.modcomment', 'not like', $comment);
                    }
                }
            });
        }

        return ['co='.rawurlencode($co)];
    }

    private function hasWildcard(string $text): bool
    {
        return str_contains($text, '*')
            || str_contains($text, '?')
            || str_contains($text, '%')
            || str_contains($text, '_');
    }
}
