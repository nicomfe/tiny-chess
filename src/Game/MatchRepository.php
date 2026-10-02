<?php

declare(strict_types=1);

namespace Chess\Game;

use PDO;

final class MatchRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function insert(GameMatch $match): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO matches
                (id, status, time_control_seconds, creator_color, creator_token_hash, created_at)
             VALUES (:id, :status, :time_control_seconds, :creator_color, :creator_token_hash, :created_at)',
        );

        $statement->execute([
            'id' => $match->id,
            'status' => $match->status->value,
            'time_control_seconds' => $match->timeControl->value,
            'creator_color' => $match->creatorColor->value,
            'creator_token_hash' => $match->creatorTokenHash,
            'created_at' => $match->createdAt->format('Y-m-d H:i:s.v'),
        ]);
    }

    public function find(string $matchId): ?GameMatch
    {
        if (!MatchId::isWellFormed($matchId)) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT * FROM matches WHERE id = :id');
        $statement->execute(['id' => $matchId]);
        $row = $statement->fetch();

        return is_array($row) ? GameMatch::fromRow($row) : null;
    }
}
