import Foundation
import Testing
@testable import Scenarisation

struct BoardDropTests {
    /// Trois moments en liste (A, B, C), hauts de 100 pt et espacés de 20 pt.
    private func listFrames() -> [DropSlot: CGRect] {
        Dictionary(uniqueKeysWithValues: ["A", "B", "C"].enumerated().map { index, id in
            (DropSlot(moment: id, target: .moment), CGRect(x: 0, y: CGFloat(index) * 120, width: 400, height: 100))
        })
    }

    @Test func movingMomentIgnoresItsOwnFrameUnderThePointer() {
        let motion = BoardMotion()
        motion.frames = listFrames()
        let drag = BoardDrag(moment: "A")
        // Début du geste : A n’a pas encore bougé, rien ne change de place.
        motion.update(drag, location: CGPoint(x: 50, y: 10), translation: CGSize(width: 0, height: 5))
        #expect(motion.slot == nil)
        // La carte A suit le pointeur et signale désormais un cadre qui le contient toujours.
        motion.frames[DropSlot(moment: "A", target: .moment)] = CGRect(x: 0, y: 190, width: 400, height: 100)
        motion.update(drag, location: CGPoint(x: 50, y: 200), translation: CGSize(width: 0, height: 190))
        #expect(motion.slot == DropSlot(moment: "C", target: .moment) && !motion.after)
        motion.update(drag, location: CGPoint(x: 50, y: 300), translation: CGSize(width: 0, height: 290))
        #expect(motion.slot == DropSlot(moment: "C", target: .moment) && motion.after)
        motion.reset()
        #expect(motion.drag == nil && motion.offset == .zero && motion.slot == nil)
    }

    @Test func columnsUseTheHorizontalAxis() {
        let motion = BoardMotion()
        motion.frames = Dictionary(uniqueKeysWithValues: ["A", "B", "C"].enumerated().map { index, id in
            (DropSlot(moment: id, target: .moment, horizontal: true), CGRect(x: CGFloat(index) * 480, y: 0, width: 460, height: 900))
        })
        let drag = BoardDrag(moment: "C")
        motion.update(drag, location: CGPoint(x: 1000, y: 20), translation: .zero)
        motion.update(drag, location: CGPoint(x: 100, y: 700), translation: CGSize(width: -900, height: 680))
        #expect(motion.slot == DropSlot(moment: "A", target: .moment, horizontal: true) && !motion.after)
    }

    @Test func activityFollowsThePointerAcrossMoments() {
        let motion = BoardMotion()
        var frames = listFrames()
        frames[DropSlot(moment: "A", target: .activity("a1"))] = CGRect(x: 10, y: 10, width: 380, height: 30)
        frames[DropSlot(moment: "A", target: .activity("a2"))] = CGRect(x: 10, y: 50, width: 380, height: 30)
        frames[DropSlot(moment: "B", target: .activity("b1"))] = CGRect(x: 10, y: 130, width: 380, height: 30)
        frames[DropSlot(moment: "C", target: .end)] = CGRect(x: 10, y: 300, width: 80, height: 20)
        motion.frames = frames
        let drag = BoardDrag(moment: "A", activity: "a1")
        motion.update(drag, location: CGPoint(x: 50, y: 20), translation: .zero)
        #expect(motion.slot == nil)
        // Sa propre place suit le pointeur : elle ne doit pas masquer la voisine.
        motion.frames[DropSlot(moment: "A", target: .activity("a1"))] = CGRect(x: 10, y: 60, width: 380, height: 30)
        motion.update(drag, location: CGPoint(x: 50, y: 70), translation: CGSize(width: 0, height: 50))
        #expect(motion.slot == DropSlot(moment: "A", target: .activity("a2")) && motion.after)
        motion.update(drag, location: CGPoint(x: 50, y: 135), translation: CGSize(width: 0, height: 115))
        #expect(motion.slot == DropSlot(moment: "B", target: .activity("b1")) && !motion.after)
        // Moment sans autre activité : dépôt à la fin.
        motion.update(drag, location: CGPoint(x: 50, y: 260), translation: CGSize(width: 0, height: 240))
        #expect(motion.slot == DropSlot(moment: "C", target: .end))
        // Loin de tout moment : rien.
        motion.update(drag, location: CGPoint(x: 900, y: 260), translation: CGSize(width: 850, height: 240))
        #expect(motion.slot == nil)
    }

    @Test func momentsReorder() {
        var scenario = Scenario.template()
        scenario.moments = (1...3).map { Moment.blank(title: "M\($0)") }
        let ids = scenario.moments.map(\.id)
        #expect(BoardDropTarget.moment.apply(BoardDrag(moment: ids[0]), to: ids[2], after: true, in: &scenario))
        #expect(scenario.moments.map(\.id) == [ids[1], ids[2], ids[0]])
        #expect(BoardDropTarget.moment.apply(BoardDrag(moment: ids[0]), to: ids[1], after: false, in: &scenario))
        #expect(scenario.moments.map(\.id) == ids)
        #expect(!BoardDropTarget.moment.apply(BoardDrag(moment: ids[0]), to: ids[1], after: false, in: &scenario))
    }

    @Test func activitiesReorderInsideAMoment() {
        var scenario = Scenario.template()
        let first = Activity.blank(), second = Activity.blank(), third = Activity.blank()
        scenario.moments[0].activities = [first, second, third]
        let otherMoments = Array(scenario.moments.dropFirst())
        let source = scenario.moments[0].id
        let drag = BoardDrag(moment: source, activity: first.id)
        #expect(BoardDropTarget.activity(third.id).apply(drag, to: source, after: true, in: &scenario))
        #expect(scenario.moments[0].activities == [second, third, first])
        #expect(BoardDropTarget.activity(second.id).apply(drag, to: source, after: false, in: &scenario))
        #expect(scenario.moments[0].activities == [first, second, third])
        #expect(!BoardDropTarget.activity(second.id).apply(drag, to: source, after: false, in: &scenario))
        #expect(Array(scenario.moments.dropFirst()) == otherMoments)
    }

    @Test func activitiesMoveToAnotherMoment() {
        var scenario = Scenario.template()
        let original = scenario.moments
        let item = original[0].activities[0], destination = original[1]
        let drag = BoardDrag(moment: original[0].id, activity: item.id)
        #expect(!BoardDropTarget.moment.apply(drag, to: destination.id, after: false, in: &scenario))
        #expect(BoardDropTarget.activity(destination.activities[0].id).apply(drag, to: destination.id, after: false, in: &scenario))
        #expect(scenario.moments[1].activities == [item] + destination.activities)
        #expect(!scenario.moments[0].activities.contains(item))
        let back = BoardDrag(moment: destination.id, activity: item.id)
        #expect(BoardDropTarget.end.apply(back, to: original[0].id, after: true, in: &scenario))
        #expect(scenario.moments[0].activities.last == item)
    }
}
