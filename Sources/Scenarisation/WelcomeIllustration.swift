import SwiftUI

/// Carnet de conception illustré, dessiné dans un même repère pour garder une perspective cohérente.
struct WelcomeIllustration: View {
    @Environment(\.colorScheme) private var colorScheme

    private var dark: Bool { colorScheme == .dark }
    private var paper: Color { Color(hex: dark ? 0x424956 : 0xfffdf6) }
    private var pageEdge: Color { Color(hex: dark ? 0x606775 : 0xe3dfd3) }
    private var ink: Color { Color(hex: dark ? 0xd6dee6 : 0x596b79) }
    private var cover: Color { Color(hex: dark ? 0x263c49 : 0x477282) }

    var body: some View {
        Canvas { context, size in
            // Conserver les proportions, même si le conteneur est redimensionné.
            let scale = min(size.width / 360, size.height / 260)
            context.translateBy(x: (size.width - 360 * scale) / 2, y: (size.height - 260 * scale) / 2)
            context.scaleBy(x: scale, y: scale)

            var ground = context
            ground.addFilter(.blur(radius: 11))
            ground.fill(Path(ellipseIn: CGRect(x: 41, y: 216, width: 284, height: 18)),
                        with: .color(.black.opacity(dark ? 0.22 : 0.10)))

            planningNote(in: &context)

            var book = context
            book.translateBy(x: 177, y: 142)
            book.rotate(by: .degrees(-9))
            book.translateBy(x: -150, y: -86)
            notebook(in: &book)

            drawingPencil(in: &context)
        }
        .accessibilityHidden(true)
    }

    private func notebook(in context: inout GraphicsContext) {
        let silhouette = page(left: true).union(page(left: false))

        // Une ombre commune, une couverture reliée et des tranches fines sous les pages.
        var binding = context
        binding.translateBy(x: 0, y: 7)
        var castShadow = binding
        castShadow.addFilter(.shadow(color: .black.opacity(dark ? 0.27 : 0.13), radius: 8, x: 2, y: 6))
        castShadow.fill(silhouette, with: .color(cover))
        binding.stroke(silhouette, with: .color(cover), style: StrokeStyle(lineWidth: 3, lineJoin: .round))

        for offset in [4.8, 2.8] {
            var edges = context
            edges.translateBy(x: 0, y: offset)
            edges.fill(silhouette, with: .color(pageEdge))
            edges.stroke(bottomEdge(left: true), with: .color(ink.opacity(0.16)), lineWidth: 0.65)
            edges.stroke(bottomEdge(left: false), with: .color(ink.opacity(0.16)), lineWidth: 0.65)
        }
        bookmark(in: &context)

        let crease = Color(hex: dark ? 0x343b46 : 0xeae5da)
        for left in [true, false] {
            let leaf = page(left: left)
            let colors = left ? [paper, paper, crease] : [crease, paper, paper]
            let start = CGPoint(x: left ? 0 : 150, y: 80)
            let end = CGPoint(x: left ? 150 : 300, y: 80)
            context.fill(leaf, with: .linearGradient(Gradient(stops: [
                .init(color: colors[0], location: 0),
                .init(color: colors[1], location: left ? 0.87 : 0.13),
                .init(color: colors[2], location: 1)
            ]), startPoint: start, endPoint: end))
            context.stroke(leaf, with: .color(ink.opacity(dark ? 0.18 : 0.12)), lineWidth: 0.65)
        }

        var left = context
        left.clip(to: page(left: true))
        leftPage(in: &left)
        var right = context
        right.clip(to: page(left: false))
        rightPage(in: &right)

        var fold = Path()
        fold.move(to: foldCurve.start)
        fold.addCurve(to: foldCurve.end, control1: foldCurve.firstControl, control2: foldCurve.secondControl)
        context.stroke(fold, with: .color(ink.opacity(dark ? 0.30 : 0.15)), lineWidth: 0.8)
        var highlight = fold
        highlight = highlight.offsetBy(dx: 1.2, dy: 0)
        context.stroke(highlight, with: .color(.white.opacity(dark ? 0.10 : 0.70)), lineWidth: 0.8)
    }

    private struct PaperCurve {
        let start: CGPoint
        let firstControl: CGPoint
        let secondControl: CGPoint
        let end: CGPoint

        func point(at t: CGFloat) -> CGPoint {
            let s = 1 - t
            return CGPoint(
                x: s * s * s * start.x + 3 * s * s * t * firstControl.x + 3 * s * t * t * secondControl.x + t * t * t * end.x,
                y: s * s * s * start.y + 3 * s * s * t * firstControl.y + 3 * s * t * t * secondControl.y + t * t * t * end.y)
        }
    }

    // Les bords, le pli et tous les tracés utilisent ces mêmes courbes.
    private var topCurve: PaperCurve {
        PaperCurve(start: CGPoint(x: 7, y: 14), firstControl: CGPoint(x: 53, y: 1),
                   secondControl: CGPoint(x: 107, y: 0), end: CGPoint(x: 150, y: 20))
    }
    private var bottomCurve: PaperCurve {
        PaperCurve(start: CGPoint(x: 8, y: 161), firstControl: CGPoint(x: 55, y: 148),
                   secondControl: CGPoint(x: 109, y: 150), end: CGPoint(x: 150, y: 169))
    }
    private var foldCurve: PaperCurve {
        PaperCurve(start: topCurve.end, firstControl: CGPoint(x: 147.5, y: 67),
                   secondControl: CGPoint(x: 147.5, y: 123), end: bottomCurve.end)
    }

    private func page(left: Bool) -> Path {
        func mirrored(_ point: CGPoint) -> CGPoint {
            CGPoint(x: left ? point.x : 300 - point.x, y: point.y)
        }
        let top = topCurve
        let bottom = bottomCurve
        let fold = foldCurve
        var path = Path()
        path.move(to: top.end)
        path.addCurve(to: mirrored(top.start), control1: mirrored(top.secondControl), control2: mirrored(top.firstControl))
        path.addQuadCurve(to: mirrored(CGPoint(x: 4, y: 18)), control: mirrored(CGPoint(x: 4, y: 14)))
        path.addLine(to: mirrored(CGPoint(x: 4, y: 157)))
        path.addQuadCurve(to: mirrored(bottom.start), control: mirrored(CGPoint(x: 4, y: 162)))
        path.addCurve(to: bottom.end, control1: mirrored(bottom.firstControl), control2: mirrored(bottom.secondControl))
        path.addCurve(to: fold.start, control1: fold.secondControl, control2: fold.firstControl)
        path.closeSubpath()
        return path
    }

    private func bottomEdge(left: Bool) -> Path {
        let edge = bottomCurve
        func mirrored(_ point: CGPoint) -> CGPoint { CGPoint(x: left ? point.x : 300 - point.x, y: point.y) }
        var path = Path()
        path.move(to: mirrored(edge.start))
        path.addCurve(to: edge.end, control1: mirrored(edge.firstControl), control2: mirrored(edge.secondControl))
        return path
    }

    /// Projection d’une feuille plane sur la surface délimitée par ses deux bords et son pli.
    private func onPage(_ point: CGPoint) -> CGPoint {
        let left = point.x <= 150
        let u = (left ? point.x : 300 - point.x) / 150
        let v = point.y / 160
        let top = topCurve.point(at: u)
        let bottom = bottomCurve.point(at: u)
        let fold = foldCurve.point(at: v)
        let flatFold = CGPoint(x: 150, y: topCurve.end.y * (1 - v) + bottomCurve.end.y * v)
        let x = top.x * (1 - v) + bottom.x * v
        let y = top.y * (1 - v) + bottom.y * v
        return CGPoint(x: (left ? x : 300 - x) + (fold.x - flatFold.x) * u,
                       y: y + (fold.y - flatFold.y) * u)
    }

    /// Déformer le contour entier, y compris les arcs, les coches et l’épaisseur des traits.
    private func printedPath(_ source: Path) -> Path {
        var result = Path()
        var current = CGPoint.zero
        var origin = CGPoint.zero
        func appendLine(to end: CGPoint) {
            let start = current
            let steps = max(1, Int(ceil(hypot(end.x - start.x, end.y - start.y) / 2)))
            for step in 1...steps {
                let t = CGFloat(step) / CGFloat(steps)
                result.addLine(to: onPage(CGPoint(x: start.x * (1 - t) + end.x * t, y: start.y * (1 - t) + end.y * t)))
            }
            current = end
        }
        source.forEach { element in
            switch element {
            case .move(let point):
                result.move(to: onPage(point)); current = point; origin = point
            case .line(let point):
                appendLine(to: point)
            case .quadCurve(let end, let control):
                let start = current
                for step in 1...32 {
                    let t = CGFloat(step) / 32
                    let s = 1 - t
                    result.addLine(to: onPage(CGPoint(x: s * s * start.x + 2 * s * t * control.x + t * t * end.x,
                                                    y: s * s * start.y + 2 * s * t * control.y + t * t * end.y)))
                }
                current = end
            case .curve(let end, let first, let second):
                let curve = PaperCurve(start: current, firstControl: first, secondControl: second, end: end)
                for step in 1...32 { result.addLine(to: onPage(curve.point(at: CGFloat(step) / 32))) }
                current = end
            case .closeSubpath:
                appendLine(to: origin); result.closeSubpath()
            }
        }
        return result
    }

    private func printedStroke(_ source: Path, in context: inout GraphicsContext, color: Color, weight: CGFloat) {
        let outline = source.strokedPath(StrokeStyle(lineWidth: weight, lineCap: .round, lineJoin: .round))
        context.fill(printedPath(outline), with: .color(color))
    }

    private func printedLine(in context: inout GraphicsContext, x: CGFloat, y: CGFloat, width: CGFloat,
                             color: Color, weight: CGFloat = 1.8) {
        var line = Path()
        line.move(to: CGPoint(x: x, y: y)); line.addLine(to: CGPoint(x: x + width, y: y))
        printedStroke(line, in: &context, color: color, weight: weight)
    }

    private func leftPage(in context: inout GraphicsContext) {
        printedLine(in: &context, x: 25, y: 35, width: 49, color: ink.opacity(0.72), weight: 3)
        printedLine(in: &context, x: 25, y: 46, width: 83, color: ink.opacity(0.24), weight: 1.6)

        // Les six couleurs apparaissent comme un schéma imprimé dans le carnet.
        let center = CGPoint(x: 77, y: 93)
        let segments: [(String, Double)] = [("read", 100), ("investigate", 30), ("practice", 70),
                                           ("produce", 35), ("discuss", 75), ("collaborate", 50)]
        var start = -90.0
        for (type, angle) in segments {
            var sector = Path()
            sector.move(to: center)
            sector.addArc(center: center, radius: 30, startAngle: .degrees(start), endAngle: .degrees(start + angle), clockwise: false)
            sector.closeSubpath()
            context.fill(printedPath(sector), with: .color(StudioStyle.color(type).opacity(dark ? 0.94 : 0.88)))
            printedStroke(sector, in: &context, color: paper.opacity(0.9), weight: 0.65)
            start += angle
        }
        printedLine(in: &context, x: 27, y: 140, width: 93, color: ink.opacity(0.25), weight: 1.5)
        printedLine(in: &context, x: 27, y: 148, width: 66, color: ink.opacity(0.19), weight: 1.5)
    }

    private func rightPage(in context: inout GraphicsContext) {
        printedLine(in: &context, x: 176, y: 35, width: 60, color: ink.opacity(0.72), weight: 3)
        printedLine(in: &context, x: 176, y: 46, width: 83, color: ink.opacity(0.24), weight: 1.6)
        let types = ["read", "investigate", "practice"]
        for (index, type) in types.enumerated() {
            let y = 69.0 + CGFloat(index) * 29
            let accent = StudioStyle.color(type)
            let center = CGPoint(x: 183, y: y + 5)
            let disc = Path(ellipseIn: CGRect(x: center.x - 7, y: center.y - 7, width: 14, height: 14))
            context.fill(printedPath(disc), with: .color(accent.opacity(dark ? 0.28 : 0.17)))
            var check = Path()
            check.move(to: CGPoint(x: center.x - 2.7, y: center.y))
            check.addLine(to: CGPoint(x: center.x - 0.6, y: center.y + 2))
            check.addLine(to: CGPoint(x: center.x + 3.1, y: center.y - 2.4))
            printedStroke(check, in: &context, color: accent, weight: 1.4)
            printedLine(in: &context, x: 199, y: y + 1, width: index == 1 ? 58 : 67, color: ink.opacity(0.42), weight: 2.1)
            printedLine(in: &context, x: 199, y: y + 9, width: index == 2 ? 42 : 52, color: ink.opacity(0.20), weight: 1.5)
        }
        printedLine(in: &context, x: 199, y: 143, width: 46, color: StudioStyle.color("collaborate").opacity(0.45), weight: 4)
    }

    private func planningNote(in context: inout GraphicsContext) {
        var note = context
        note.translateBy(x: 100, y: 19)
        note.rotate(by: .degrees(8))
        let shape = Path(roundedRect: CGRect(x: 0, y: 0, width: 88, height: 92), cornerRadius: 5)
        var castShadow = note
        castShadow.addFilter(.shadow(color: .black.opacity(dark ? 0.15 : 0.07), radius: 5, x: 0, y: 3))
        castShadow.fill(shape, with: .color(Color(hex: dark ? 0x594f48 : 0xf3e7cc)))
        var headline = Path()
        headline.move(to: CGPoint(x: 13, y: 17)); headline.addLine(to: CGPoint(x: 47, y: 17))
        note.stroke(headline, with: .color(Color(hex: dark ? 0xe2c38a : 0xa88d53).opacity(0.8)),
                    style: StrokeStyle(lineWidth: 2.5, lineCap: .round))
        for (row, width) in [59.0, 48, 55].enumerated() {
            var line = Path()
            let y = 29.0 + Double(row) * 9
            line.move(to: CGPoint(x: 13, y: y)); line.addLine(to: CGPoint(x: 13 + width, y: y))
            note.stroke(line, with: .color(ink.opacity(0.20)), style: StrokeStyle(lineWidth: 1.3, lineCap: .round))
        }
    }

    private func bookmark(in context: inout GraphicsContext) {
        let x: CGFloat = 190
        let y: CGFloat = 153
        var ribbon = Path()
        ribbon.move(to: CGPoint(x: x, y: y))
        ribbon.addLine(to: CGPoint(x: x + 9, y: y))
        ribbon.addLine(to: CGPoint(x: x + 9, y: y + 38))
        ribbon.addLine(to: CGPoint(x: x + 4.5, y: y + 34))
        ribbon.addLine(to: CGPoint(x: x, y: y + 38))
        ribbon.closeSubpath()
        context.fill(ribbon, with: .linearGradient(Gradient(colors: [StudioStyle.color("investigate"), Color(hex: 0xd66f74)]),
                     startPoint: CGPoint(x: x, y: y), endPoint: CGPoint(x: x + 9, y: y + 38)))
    }

    private func drawingPencil(in context: inout GraphicsContext) {
        var pencil = context
        pencil.translateBy(x: 321, y: 71)
        pencil.rotate(by: .degrees(12))
        var silhouette = Path(roundedRect: CGRect(x: 0, y: -8, width: 8.5, height: 115), cornerRadius: 3)
        var tip = Path()
        tip.move(to: CGPoint(x: 0, y: 107)); tip.addLine(to: CGPoint(x: 4.25, y: 121))
        tip.addLine(to: CGPoint(x: 8.5, y: 107)); tip.closeSubpath()
        silhouette.addPath(tip)
        var castShadow = pencil
        castShadow.addFilter(.shadow(color: .black.opacity(dark ? 0.30 : 0.18), radius: 2.5, x: 2.5, y: 3))
        castShadow.fill(silhouette, with: .color(Color(hex: 0xe5ae43)))
        pencil.fill(Path(CGRect(x: 0, y: 5, width: 8.5, height: 102)),
                    with: .linearGradient(Gradient(stops: [
                        .init(color: Color(hex: 0xe1a63e), location: 0),
                        .init(color: Color(hex: 0xf9ce70), location: 0.32),
                        .init(color: Color(hex: 0xf0b94f), location: 0.55),
                        .init(color: Color(hex: 0xd89a32), location: 1)
                    ]), startPoint: .zero, endPoint: CGPoint(x: 8.5, y: 0)))
        pencil.fill(Path(roundedRect: CGRect(x: 0, y: -8, width: 8.5, height: 13), cornerRadius: 3),
                    with: .linearGradient(Gradient(colors: [Color(hex: 0xeea69e), Color(hex: 0xd8827e)]),
                        startPoint: .zero, endPoint: CGPoint(x: 8.5, y: 0)))
        pencil.fill(Path(CGRect(x: 0, y: 2, width: 8.5, height: 6)),
                    with: .linearGradient(Gradient(colors: [Color(hex: 0xaebac3), Color(hex: 0xf5f6ef), Color(hex: 0xa8b5bc)]),
                        startPoint: .zero, endPoint: CGPoint(x: 8.5, y: 0)))
        pencil.fill(tip, with: .color(Color(hex: 0xe4c5a0)))
        var graphite = Path()
        graphite.move(to: CGPoint(x: 2.9, y: 116.5)); graphite.addLine(to: CGPoint(x: 4.25, y: 121))
        graphite.addLine(to: CGPoint(x: 5.6, y: 116.5)); graphite.closeSubpath()
        pencil.fill(graphite, with: .color(Color(hex: 0x475260)))
    }
}
