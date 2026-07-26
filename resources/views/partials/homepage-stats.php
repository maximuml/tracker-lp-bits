<?php
/**
 * @var array<string, array<int, array{label: string, value: string}>> $sections
 */
?>
<style>
.homepage-stats {
    display: inline-block;
    width: 100%;
    max-width: 960px;
    text-align: left;
    margin: 8px 0 16px;
}
.homepage-stats-group {
    margin: 16px 0 8px;
    font-size: 15px;
    font-weight: bold;
    opacity: 0.9;
}
.homepage-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 12px;
}
.homepage-stats-card {
    border: 1px solid rgba(127, 127, 127, 0.35);
    border-radius: 6px;
    padding: 12px;
    background: rgba(127, 127, 127, 0.05);
    text-align: center;
    min-height: 70px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.homepage-stats-label {
    font-size: 12px;
    line-height: 1.3;
    opacity: 0.85;
    margin-bottom: 6px;
    word-break: break-word;
}
.homepage-stats-value {
    font-size: 18px;
    font-weight: bold;
    line-height: 1.2;
}
@media (max-width: 420px) {
    .homepage-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
<div class="homepage-stats">
<?php foreach ($sections as $groupTitle => $items): ?>
    <h3 class="homepage-stats-group"><?php echo htmlspecialchars((string)$groupTitle); ?></h3>
    <div class="homepage-stats-grid">
<?php foreach ($items as $item): ?>
        <div class="homepage-stats-card">
            <div class="homepage-stats-label"><?php echo $item['label']; ?></div>
            <div class="homepage-stats-value"><?php echo htmlspecialchars((string)$item['value']); ?></div>
        </div>
<?php endforeach; ?>
    </div>
<?php endforeach; ?>
</div>
