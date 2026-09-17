import domtoimage from 'dom-to-image-more';
import jsPDF from 'jspdf';

/**
 * Client-side invoice PDF generation: captures the InvoicePrintDocument DOM and
 * rasterizes it into a single-page PDF, entirely in the browser (no backend PDF
 * route involved).
 *
 * Uses dom-to-image-more rather than html2canvas: html2canvas re-implements its
 * own text shaping when it draws to the canvas, and that re-implementation does
 * not handle Arabic's contextual letterforms — captured Arabic came out with
 * disconnected/wrong glyphs even with a real embedded font. dom-to-image-more
 * instead serializes the live DOM into an SVG <foreignObject> and lets the
 * browser's own (correct) text renderer draw it, so Arabic/RTL text comes out
 * exactly as it renders on screen.
 */
function slugify(value) {
    return String(value ?? '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}

function filenameFor(invoice) {
    const base = slugify(invoice?.invoice_number) || 'invoice';

    return `${base}.pdf`;
}

async function waitForFonts() {
    if (!document.fonts?.ready) {
        return;
    }

    try {
        await document.fonts.load('700 16px TajawalPrint');
        await document.fonts.load('400 16px TajawalPrint');
        await document.fonts.ready;
    } catch {
        // Font API not fully supported — proceed with whatever is available.
    }
}

async function renderSinglePagePdf(element, invoice) {
    await waitForFonts();

    const scale = 2;
    const naturalWidth = element.offsetWidth;
    const naturalHeight = element.offsetHeight;

    const canvas = await domtoimage.toCanvas(element, {
        width: naturalWidth * scale,
        height: naturalHeight * scale,
        bgcolor: '#ffffff',
        style: {
            transform: `scale(${scale})`,
            transformOrigin: 'top left',
            width: `${naturalWidth}px`,
            height: `${naturalHeight}px`,
        },
    });

    const pdf = new jsPDF({ unit: 'pt', format: 'a4', orientation: 'portrait' });
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();

    const imageData = canvas.toDataURL('image/jpeg', 0.98);
    const naturalWidthPt = pageWidth;
    const naturalHeightPt = (canvas.height / canvas.width) * pageWidth;

    let drawWidth = naturalWidthPt;
    let drawHeight = naturalHeightPt;

    // Content taller than the page: shrink to fit the height instead, so the whole
    // invoice always lands on exactly one page, however long its content is.
    if (drawHeight > pageHeight) {
        drawHeight = pageHeight;
        drawWidth = (canvas.width / canvas.height) * pageHeight;
    }

    const offsetX = (pageWidth - drawWidth) / 2;
    const offsetY = 0;

    pdf.addImage(imageData, 'JPEG', offsetX, offsetY, drawWidth, drawHeight);

    return { pdf, filename: filenameFor(invoice) };
}

export function useInvoicePdf() {
    async function downloadPdf(element, invoice) {
        const { pdf, filename } = await renderSinglePagePdf(element, invoice);
        pdf.save(filename);
    }

    async function printPdf(element, invoice) {
        const { pdf } = await renderSinglePagePdf(element, invoice);
        const blobUrl = pdf.output('bloburl');

        window.open(blobUrl, '_blank');
    }

    return { downloadPdf, printPdf };
}
