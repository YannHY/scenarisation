import Foundation
import libxml2.HTMLparser

// `XMLDocument` (Foundation) n’existe que sur macOS. L’import Word, Excel et HTML partagé avec le Mac
// (`ScenarioOfficeReader`) n’en utilise qu’un petit sous-ensemble, reproduit ici au-dessus de libxml2 :
// arbre de nœuds, noms locaux, attributs et texte. Comme sur Mac, aucune entité externe ni ressource réseau
// n’est chargée, et les entités ne sont pas substituées.

class XMLNode {
    enum Kind { case document, element, text, attribute, other }
    let kind: Kind
    /// Nom qualifié (« w:t »), comme `XMLNode.name` sur Mac.
    let name: String?
    let localName: String?
    fileprivate(set) var children: [XMLNode]?
    fileprivate var content: String?

    fileprivate init(kind: Kind, name: String? = nil, localName: String? = nil, content: String? = nil) {
        self.kind = kind; self.name = name; self.localName = localName; self.content = content
    }

    /// Texte du nœud et de ses descendants.
    var stringValue: String? {
        switch kind {
        case .text, .attribute: content
        case .other: ""
        case .document, .element: (children ?? []).map { $0.kind == .other ? "" : ($0.stringValue ?? "") }.joined()
        }
    }
}

final class XMLElement: XMLNode {
    fileprivate(set) var attributes: [XMLNode]?
}

final class XMLDocument: XMLNode {
    struct Options: OptionSet, Sendable {
        let rawValue: Int
        static let nodeLoadExternalEntitiesNever = Options(rawValue: 1 << 0)
        static let documentTidyHTML = Options(rawValue: 1 << 1)
    }

    convenience init(data: Data, options: Options = []) throws {
        try self.init(bytes: data, html: options.contains(.documentTidyHTML))
    }

    convenience init(xmlString: String, options: Options = []) throws {
        try self.init(bytes: Data(xmlString.utf8), html: options.contains(.documentTidyHTML))
    }

    private init(bytes: Data, html: Bool) throws {
        super.init(kind: .document)
        let parsed: xmlDocPtr? = bytes.withUnsafeBytes { buffer in
            guard let base = buffer.baseAddress?.assumingMemoryBound(to: CChar.self) else { return nil }
            if html {
                // Équivalent de la mise en forme « tidy » du Mac : HTML tolérant, sans réseau.
                let options = HTML_PARSE_RECOVER.rawValue | HTML_PARSE_NONET.rawValue | HTML_PARSE_NOERROR.rawValue
                    | HTML_PARSE_NOWARNING.rawValue
                return htmlReadMemory(base, Int32(buffer.count), nil, "UTF-8", Int32(options))
            }
            let options = XML_PARSE_NONET.rawValue | XML_PARSE_NOERROR.rawValue | XML_PARSE_NOWARNING.rawValue
                | XML_PARSE_NOCDATA.rawValue
            return xmlReadMemory(base, Int32(buffer.count), nil, nil, Int32(options))
        }
        guard let parsed else { throw ScenarioError.invalid("Le document contient un fichier XML invalide.") }
        defer { xmlFreeDoc(parsed) }
        children = Self.nodes(from: parsed.pointee.children)
    }

    func rootElement() -> XMLElement? {
        children?.first { $0.kind == .element } as? XMLElement
    }

    private static func string(_ pointer: UnsafePointer<xmlChar>?) -> String? {
        pointer.map { String(cString: $0) }
    }

    private static func nodes(from first: xmlNodePtr?) -> [XMLNode] {
        var result: [XMLNode] = []
        var cursor = first
        while let node = cursor {
            switch node.pointee.type {
            case XML_ELEMENT_NODE:
                let local = string(node.pointee.name) ?? ""
                let prefix = node.pointee.ns.flatMap { string($0.pointee.prefix) }
                let element = XMLElement(kind: .element, name: prefix.map { "\($0):\(local)" } ?? local, localName: local)
                var attributes: [XMLNode] = []
                var attribute = node.pointee.properties
                while let current = attribute {
                    let name = string(current.pointee.name) ?? ""
                    let value = text(of: current.pointee.children)
                    let attributePrefix = current.pointee.ns.flatMap { string($0.pointee.prefix) }
                    attributes.append(XMLNode(kind: .attribute, name: attributePrefix.map { "\($0):\(name)" } ?? name,
                                              localName: name, content: value))
                    attribute = current.pointee.next
                }
                element.attributes = attributes
                element.children = nodes(from: node.pointee.children)
                result.append(element)
            case XML_TEXT_NODE, XML_CDATA_SECTION_NODE:
                result.append(XMLNode(kind: .text, content: string(node.pointee.content) ?? ""))
            case XML_ENTITY_REF_NODE:
                // Les entités ne sont pas substituées : leur nom est conservé comme du texte.
                result.append(XMLNode(kind: .text, content: "&" + (string(node.pointee.name) ?? "") + ";"))
            default:
                result.append(XMLNode(kind: .other))
            }
            cursor = node.pointee.next
        }
        return result
    }

    private static func text(of first: xmlNodePtr?) -> String {
        var value = ""
        var cursor = first
        while let node = cursor {
            value += string(node.pointee.content) ?? ""
            cursor = node.pointee.next
        }
        return value
    }
}
