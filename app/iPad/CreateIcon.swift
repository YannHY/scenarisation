import AppKit

// Icônes de l’app iPad, à partir du même dessin que l’icône Mac :
// - AppIcon : carré plein cadre de 1024 px (iPadOS applique lui-même le masque arrondi),
//   en trois apparences : claire, sombre et teintée ;
// - BrandMark : l’icône détourée, pour l’accueil et les crédits.
let destination = URL(fileURLWithPath: CommandLine.arguments[1])
let segments: [(UInt32, Double)] = [(0x4fd1c5, 100), (0xf28b88, 30), (0xb98af0, 70), (0x8fd16a, 35), (0x6aa8f2, 75), (0xf0b242, 50)]
func color(_ hex: UInt32) -> NSColor {
    NSColor(red: Double((hex >> 16) & 255) / 255, green: Double((hex >> 8) & 255) / 255, blue: Double(hex & 255) / 255, alpha: 1)
}
/// Apparence de l’icône d’app : iPadOS choisit la variante selon le réglage Apparence de l’écran d’accueil.
enum Appearance { case light, dark, tinted }
/// Variante teintée : iPadOS colore un dessin en niveaux de gris. Les six couleurs ont presque la même
/// luminosité ; des gris alternés gardent les secteurs voisins distincts.
let tintedGrays: [CGFloat] = [1.0, 0.55, 0.8, 0.42, 0.7, 0.5]
func render(_ pixels: CGFloat, fullBleed: Bool, appearance: Appearance = .light) -> Data {
    // L’icône claire doit être opaque : son contexte ignore la couche alpha. Les variantes sombre et teintée
    // gardent un fond transparent, que iPadOS remplit de son propre fond sombre.
    let opaque = fullBleed && appearance == .light
    let context = CGContext(data: nil, width: Int(pixels), height: Int(pixels), bitsPerComponent: 8, bytesPerRow: 0,
                            space: CGColorSpace(name: CGColorSpace.sRGB)!,
                            bitmapInfo: opaque ? CGImageAlphaInfo.noneSkipLast.rawValue : CGImageAlphaInfo.premultipliedLast.rawValue)!
    NSGraphicsContext.saveGraphicsState()
    NSGraphicsContext.current = NSGraphicsContext(cgContext: context, flipped: false)
    let body = fullBleed ? pixels : pixels * 824 / 1024
    let origin = (pixels - body) / 2
    let rect = NSRect(x: origin, y: origin, width: body, height: body)
    let shape = fullBleed ? NSBezierPath(rect: rect) : NSBezierPath(roundedRect: rect, xRadius: body * 0.225, yRadius: body * 0.225)
    if !fullBleed {
        NSGraphicsContext.saveGraphicsState()
        let shadow = NSShadow()
        shadow.shadowColor = NSColor.black.withAlphaComponent(0.28)
        shadow.shadowBlurRadius = pixels * 0.025
        shadow.shadowOffset = NSSize(width: 0, height: -pixels * 0.012)
        shadow.set()
        NSColor.white.setFill()
        shape.fill()
        NSGraphicsContext.restoreGraphicsState()
    }
    if appearance == .light {
        NSGradient(colors: [NSColor.white, NSColor(white: 0.9, alpha: 1)])!.draw(in: shape, angle: -90)
    }
    let center = NSPoint(x: pixels / 2, y: pixels / 2)
    // Même proportion que sur Mac : le disque occupe 88 % du corps de l’icône (le carré entier sur iPad).
    let radius = body * 0.44
    var start = 90.0
    for (index, (hex, angle)) in segments.enumerated() {
        let sector = NSBezierPath()
        sector.move(to: center)
        sector.appendArc(withCenter: center, radius: radius, startAngle: start, endAngle: start - angle, clockwise: true)
        sector.close()
        (appearance == .tinted ? NSColor(white: tintedGrays[index], alpha: 1) : color(hex)).setFill()
        sector.fill()
        start -= angle
    }
    NSGraphicsContext.restoreGraphicsState()
    return NSBitmapImageRep(cgImage: context.makeImage()!).representation(using: .png, properties: [:])!
}
let icons = destination.appendingPathComponent("AppIcon.appiconset")
try render(1024, fullBleed: true).write(to: icons.appendingPathComponent("AppIcon-1024.png"))
try render(1024, fullBleed: true, appearance: .dark).write(to: icons.appendingPathComponent("AppIcon-1024-dark.png"))
try render(1024, fullBleed: true, appearance: .tinted).write(to: icons.appendingPathComponent("AppIcon-1024-tinted.png"))
try render(512, fullBleed: false).write(to: destination.appendingPathComponent("BrandMark.imageset/BrandMark.png"))
