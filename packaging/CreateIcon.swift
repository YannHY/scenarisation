import AppKit

// Icône au gabarit macOS : corps de 824/1024 centré, coins continus, ombre douce,
// disque plein aux six couleurs des types d’apprentissage.
let destination = CommandLine.arguments[1]
try FileManager.default.createDirectory(atPath: destination, withIntermediateDirectories: true)
let segments: [(UInt32, Double)] = [(0x4fd1c5, 100), (0xf28b88, 30), (0xb98af0, 70), (0x8fd16a, 35), (0x6aa8f2, 75), (0xf0b242, 50)]
func color(_ hex: UInt32) -> NSColor {
    NSColor(red: Double((hex >> 16) & 255) / 255, green: Double((hex >> 8) & 255) / 255, blue: Double(hex & 255) / 255, alpha: 1)
}
for size in [16, 32, 128, 256, 512] {
    for scale in [1, 2] {
        let pixels = CGFloat(size * scale)
        let image = NSImage(size: NSSize(width: pixels, height: pixels))
        image.lockFocus()
        let body = pixels * 824 / 1024
        let origin = (pixels - body) / 2
        let rect = NSRect(x: origin, y: origin, width: body, height: body)
        let shape = NSBezierPath(roundedRect: rect, xRadius: body * 0.225, yRadius: body * 0.225)
        NSGraphicsContext.saveGraphicsState()
        let shadow = NSShadow()
        shadow.shadowColor = NSColor.black.withAlphaComponent(0.28)
        shadow.shadowBlurRadius = pixels * 0.025
        shadow.shadowOffset = NSSize(width: 0, height: -pixels * 0.012)
        shadow.set()
        NSColor.white.setFill()
        shape.fill()
        NSGraphicsContext.restoreGraphicsState()
        NSGradient(colors: [NSColor.white, NSColor(white: 0.9, alpha: 1)])!.draw(in: shape, angle: -90)
        let center = NSPoint(x: pixels / 2, y: pixels / 2)
        let radius = body * 0.44
        var start = 90.0
        for (hex, angle) in segments {
            let sector = NSBezierPath()
            sector.move(to: center)
            sector.appendArc(withCenter: center, radius: radius, startAngle: start, endAngle: start - angle, clockwise: true)
            sector.close()
            color(hex).setFill()
            sector.fill()
            start -= angle
        }
        if ProcessInfo.processInfo.environment["SCENARISATION_BETA"] == "1" {
            NSGraphicsContext.saveGraphicsState()
            shape.addClip()
            let ribbon = NSRect(x: origin, y: origin + body * 0.07, width: body, height: body * 0.22)
            color(0x202637).setFill()
            ribbon.fill()
            let label = NSAttributedString(string: "BÊTA", attributes: [
                .font: NSFont.systemFont(ofSize: body * 0.16, weight: .bold),
                .foregroundColor: NSColor.white
            ])
            let textSize = label.size()
            label.draw(at: NSPoint(x: ribbon.midX - textSize.width / 2, y: ribbon.midY - textSize.height / 2))
            NSGraphicsContext.restoreGraphicsState()
        }
        image.unlockFocus()
        let bitmap = NSBitmapImageRep(data: image.tiffRepresentation!)!
        let filename = "icon_\(size)x\(size)\(scale == 2 ? "@2x" : "").png"
        try bitmap.representation(using: .png, properties: [:])!.write(to: URL(fileURLWithPath: destination).appendingPathComponent(filename))
    }
}
