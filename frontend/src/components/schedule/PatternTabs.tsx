"use client";

import React from "react";

export interface PatternTab {
  label?: string;
}

type PatternTabsProps = {
  patterns: PatternTab[];
  selectedIndex: number;
  onSelectPattern: (index: number) => void;
};

export default function PatternTabs({
  patterns,
  selectedIndex,
  onSelectPattern,
}: PatternTabsProps) {
  return (
    <div className="flex gap-2 mb-4">
      {patterns.map((p, i) => (
        <button
          key={i}
          onClick={() => onSelectPattern(i)}
          className={`px-4 py-2 rounded text-left ${
            selectedIndex === i
              ? "bg-blue-600 text-white"
              : "bg-gray-200 text-gray-700"
          }`}
        >
          <div className="text-sm font-medium">パターン{i + 1}</div>
          {p.label && (
            <div className={`text-[10px] mt-0.5 ${selectedIndex === i ? 'text-blue-200' : 'text-gray-500'}`}>
              {p.label}
            </div>
          )}
        </button>
      ))}
    </div>
  );
}
