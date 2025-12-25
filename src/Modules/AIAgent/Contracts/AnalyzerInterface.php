<?php
/**
 * Analyzer Interface
 * 
 * @package Forooshyar\Modules\AIAgent\Contracts
 */

namespace Forooshyar\Modules\AIAgent\Contracts;

interface AnalyzerInterface
{
    /**
     * Run analysis on entities
     *
     * @param array $options Analysis options
     * @return array Analysis results
     */
    public function analyze(array $options = []);

    /**
     * Analyze a single entity
     *
     * @param int $entityId
     * @return array
     */
    public function analyzeEntity($entityId);

    /**
     * Get entities to analyze
     *
     * @param int $limit
     * @return array
     */
    public function getEntities($limit);

    /**
     * Build prompt for LLM
     *
     * @param array $entityData
     * @return array Messages array for LLM
     */
    public function buildPrompt(array $entityData);

    /**
     * Parse LLM response
     *
     * @param array $response
     * @return array Parsed analysis data
     */
    public function parseResponse(array $response);
}
