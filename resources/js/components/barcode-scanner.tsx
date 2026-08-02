import { LoaderCircle } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type BarcodeScannerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Fired with the decoded value; the overlay then closes. */
    onDetected: (code: string) => void;
};

// The formats worth scanning for inventory: retail EAN/UPC, the Code family, + QR.
const FORMATS = [
    'qr_code',
    'ean_13',
    'ean_8',
    'upc_a',
    'upc_e',
    'code_128',
    'code_39',
];

// Minimal shape of the native BarcodeDetector API (Chrome/Android/Edge), typed so
// we don't reach for `any`. Absent on iOS Safari / Firefox → we fall back to zxing.
type DetectedBarcode = { rawValue: string };
type NativeDetector = {
    detect: (source: CanvasImageSource) => Promise<DetectedBarcode[]>;
};
type NativeDetectorCtor = new (opts?: { formats?: string[] }) => NativeDetector;

/**
 * A camera barcode/QR scanner in a dialog. Uses the native `BarcodeDetector` when
 * available, else lazily loads `@zxing/browser` (kept out of the main bundle).
 * Client-only — the camera is started in an effect while open, and fully stopped on
 * close. Needs a secure context (HTTPS / localhost) for camera access.
 */
export function BarcodeScanner({
    open,
    onOpenChange,
    onDetected,
}: BarcodeScannerProps) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const [error, setError] = useState<string | null>(null);
    const [starting, setStarting] = useState(true);

    useEffect(() => {
        if (!open) {
            return;
        }

        let cancelled = false;
        let stream: MediaStream | null = null;
        let stopDecoding = () => {};

        const finish = (code: string) => {
            onDetected(code);
            onOpenChange(false);
        };

        const start = async () => {
            setError(null);
            setStarting(true);
            try {
                if (!navigator.mediaDevices?.getUserMedia) {
                    throw new Error('unsupported');
                }
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' },
                });
                const video = videoRef.current;
                if (cancelled || !video) {
                    return;
                }
                video.srcObject = stream;
                await video.play();
                setStarting(false);

                const NativeCtor = (
                    window as unknown as {
                        BarcodeDetector?: NativeDetectorCtor;
                    }
                ).BarcodeDetector;

                if (NativeCtor) {
                    const detector = new NativeCtor({ formats: FORMATS });
                    let frame = 0;
                    const tick = async () => {
                        if (cancelled || !videoRef.current) {
                            return;
                        }
                        try {
                            const found = await detector.detect(
                                videoRef.current,
                            );
                            if (found[0]?.rawValue) {
                                finish(found[0].rawValue);
                                return;
                            }
                        } catch {
                            // Transient detect errors between frames — keep scanning.
                        }
                        frame = requestAnimationFrame(tick);
                    };
                    frame = requestAnimationFrame(tick);
                    stopDecoding = () => cancelAnimationFrame(frame);
                    return;
                }

                // Fallback: zxing reads frames off the already-playing video.
                const { BrowserMultiFormatReader } = await import(
                    '@zxing/browser'
                );
                if (cancelled || !videoRef.current) {
                    return;
                }
                const controls =
                    await new BrowserMultiFormatReader().decodeFromVideoElement(
                        videoRef.current,
                        (result) => {
                            if (result) {
                                finish(result.getText());
                            }
                        },
                    );
                stopDecoding = () => controls.stop();
            } catch (caught) {
                if (cancelled) {
                    return;
                }
                setStarting(false);
                setError(
                    caught instanceof DOMException &&
                        caught.name === 'NotAllowedError'
                        ? 'Camera access was blocked. Allow it in your browser, or use a handheld scanner.'
                        : "Couldn't start the camera. Try a handheld scanner instead.",
                );
            }
        };

        void start();

        return () => {
            cancelled = true;
            stopDecoding();
            for (const track of stream?.getTracks() ?? []) {
                track.stop();
            }
        };
    }, [open, onDetected, onOpenChange]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Scan a barcode</DialogTitle>
                    <DialogDescription>
                        Point your camera at the item's barcode or QR code.
                    </DialogDescription>
                </DialogHeader>

                {error ? (
                    <p className="text-destructive text-sm" role="alert">
                        {error}
                    </p>
                ) : (
                    <div className="relative aspect-video overflow-hidden rounded-lg border border-border bg-muted">
                        {/** biome-ignore lint/a11y/useMediaCaption: a live camera preview has no captions. */}
                        <video
                            ref={videoRef}
                            className="size-full object-cover"
                            muted
                            playsInline
                        />
                        {starting ? (
                            <div className="absolute inset-0 flex items-center justify-center gap-2 text-muted-foreground text-sm">
                                <LoaderCircle className="size-4 animate-spin" />
                                Starting camera…
                            </div>
                        ) : null}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
