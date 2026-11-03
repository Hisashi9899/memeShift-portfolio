export function Loading() {
  return (
    <div className="flex items-center justify-center py-20">
      <div className="flex flex-col items-center gap-3">
        <div className="w-5 h-5 border-2 border-surface-border border-t-primary-400 rounded-full animate-spin duration-500" />
        <span className="text-xs text-gray-400 font-medium">読み込み中...</span>
      </div>
    </div>
  );
}
