/**
 * Réduit une photo avant l'envoi (1000 px max, WebP/JPEG) : une photo de téléphone de plusieurs Mo
 * deviendrait un envoi très lent en 3G/4G. En cas d'échec, le fichier d'origine est envoyé tel quel.
 */
export async function shrinkImage(file: File, maxSide = 1000): Promise<File> {
    try {
        if (!file.type.startsWith('image/')) return file;
        const bitmap = await createImageBitmap(file);
        const ratio = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * ratio);
        canvas.height = Math.round(bitmap.height * ratio);
        canvas.getContext('2d')?.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob: Blob | null = await new Promise((res) => canvas.toBlob(res, 'image/webp', 0.8));
        if (!blob || blob.size >= file.size) return file;
        return new File([blob], file.name.replace(/\.\w+$/, '') + '.webp', { type: 'image/webp' });
    } catch {
        return file;
    }
}
