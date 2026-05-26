/**
 * Helper utility to trigger a browser download for a Blob response.
 */
export function downloadBlob(data: unknown, filename: string, mimeType = 'application/pdf'): void {
  const blob = data instanceof Blob ? data : new Blob([data as any], { type: mimeType });
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.click();
  window.URL.revokeObjectURL(url);
}
